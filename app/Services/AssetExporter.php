<?php

namespace App\Services;

use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Export register aset (katalog) ke CSV, XLSX, atau PDF sesuai filter yang aktif. */
class AssetExporter
{
    /** Batas baris PDF agar proses render tetap wajar; format lain tanpa batas. */
    public const PDF_MAX_ROWS = 1000;

    public const HEADINGS = ['Asset Tag', 'Nama', 'Kategori', 'Serial Number', 'Status', 'Kondisi', 'Lokasi', 'Departemen', 'Pemegang', 'Tgl Beli', 'Nilai Beli', 'Garansi s.d.'];

    public function __construct(private Builder $query, private array $filters = [])
    {
        $this->query->with('category', 'location', 'department', 'activeAssignment.employee', 'activeLoan.borrower')->orderBy('asset_tag');
    }

    public function download(string $format): Response|StreamedResponse
    {
        return match ($format) {
            'xlsx' => $this->xlsx(),
            'pdf' => $this->pdf(),
            default => $this->csv(),
        };
    }

    private function row(Asset $a): array
    {
        return [
            $a->asset_tag, $a->name, $a->category->name, $a->serial_number, $a->status->label(), $a->condition->label(),
            $a->location->name, $a->department?->name, $a->currentHolder()?->name,
            $a->purchase_date?->format('Y-m-d'), $a->purchase_cost !== null ? (float) $a->purchase_cost : null,
            $a->warranty_end_date?->format('Y-m-d'),
        ];
    }

    private function filename(string $ext): string
    {
        return 'register-aset-'.now()->format('Ymd-His').'.'.$ext;
    }

    private function csv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Dibuat pada', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, self::HEADINGS);
            $this->query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $a) {
                    fputcsv($out, $this->row($a));
                }
            });
            fclose($out);
        }, $this->filename('csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(): StreamedResponse
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator(config('app.name'))->setTitle('Register Aset');
        $sheet = $book->getActiveSheet()->setTitle('Register Aset');
        $lastCol = Coordinate::stringFromColumnIndex(count(self::HEADINGS));

        $sheet->setCellValue('A1', 'Register Aset — '.config('app.name'));
        $sheet->setCellValue('A2', 'Dibuat pada '.now()->format('d M Y H:i').($this->filters ? ' · Filter: '.implode(', ', $this->filters) : ''));
        $sheet->mergeCells("A1:{$lastCol}1")->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $sheet->fromArray(self::HEADINGS, null, 'A4');
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '131B2E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $r = 5;
        $this->query->chunk(500, function ($chunk) use ($sheet, &$r) {
            foreach ($chunk as $a) {
                $sheet->fromArray($this->row($a), null, "A{$r}", true);
                $r++;
            }
        });
        $last = max(5, $r - 1);

        $sheet->getStyle("K5:K{$last}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A4:{$lastCol}{$last}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        if ($r > 5) {
            $sheet->setCellValue("J{$r}", 'Total');
            $sheet->setCellValue("K{$r}", "=SUM(K5:K{$last})");
            $sheet->getStyle("J{$r}:K{$r}")->getFont()->setBold(true);
            $sheet->getStyle("K{$r}")->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (range(1, count(self::HEADINGS)) as $col) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:{$lastCol}{$last}");

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $this->filename('xlsx'), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function pdf(): Response
    {
        $total = (clone $this->query)->count();
        $assets = $this->query->limit(self::PDF_MAX_ROWS)->get();

        return Pdf::loadView('assets.export-pdf', [
            'assets' => $assets,
            'rows' => $assets->map(fn (Asset $a) => $this->row($a)),
            'total' => $total,
            'truncated' => $total > self::PDF_MAX_ROWS,
            'filters' => $this->filters,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($this->filename('pdf'));
    }
}
