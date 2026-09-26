<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Register Aset</title>
    <style>
        @page { margin: 18mm 12mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #0b1c30; }
        .head { border-bottom: 2px solid #131b2e; padding-bottom: 6px; margin-bottom: 8px; }
        .head h1 { font-size: 15px; margin: 0; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #131b2e; color: #fff; text-align: left; padding: 4px; font-size: 8px; }
        td { padding: 3px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        tfoot td { font-weight: bold; border-top: 1.5px solid #131b2e; background: #fff; }
        .note { margin-top: 6px; color: #b45309; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Register Aset — {{ config('app.name') }}</h1>
        <div class="muted">
            Dibuat {{ now()->format('d M Y H:i') }} oleh {{ auth()->user()?->name }} · {{ number_format($total, 0, ',', '.') }} aset
            @if ($filters) · Filter: {{ implode(', ', $filters) }} @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                @foreach (\App\Services\AssetExporter::HEADINGS as $h)
                    <th @class(['num' => $h === 'Nilai Beli'])>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    @foreach ($row as $c => $value)
                        @if ($c === 10)
                            <td class="num">{{ $value !== null ? number_format($value, 0, ',', '.') : '-' }}</td>
                        @else
                            <td @class(['mono' => $c === 0 || $c === 3])>{{ $value ?? '-' }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="13" class="muted">Tidak ada aset sesuai filter.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="11" class="num">Total nilai beli (baris ditampilkan)</td>
                    <td class="num">Rp {{ number_format($rows->sum(fn ($r) => $r[10] ?? 0), 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($truncated)
        <p class="note">Ditampilkan {{ number_format(\App\Services\AssetExporter::PDF_MAX_ROWS, 0, ',', '.') }} dari {{ number_format($total, 0, ',', '.') }} aset. Gunakan export XLSX/CSV untuk data lengkap, atau persempit filter.</p>
    @endif

    <div class="footer">{{ config('app.name') }} · Register Aset · {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
