<?php

namespace Tests\Unit\Models;

use App\Models\Attachment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttachmentTest extends TestCase
{
    public static function sizes(): array
    {
        return [
            'nol' => [0, '0 B'],
            'byte' => [512, '512 B'],
            'batas KB' => [1023, '1023 B'],
            '1 KB' => [1024, '1 KB'],
            '1.5 KB' => [1536, '1.5 KB'],
            '1 MB' => [1024 * 1024, '1 MB'],
            '5 MB' => [5 * 1024 * 1024, '5 MB'],
            '2.3 GB' => [(int) (2.3 * 1024 ** 3), '2.3 GB'],
            '3 TB' => [3 * 1024 ** 4, '3 TB'],
        ];
    }

    #[DataProvider('sizes')]
    public function test_human_size(int $bytes, string $expected): void
    {
        $this->assertSame($expected, new Attachment(['size_bytes' => $bytes])->humanSize());
    }

    public function test_kategori_lampiran_lengkap(): void
    {
        $this->assertSame(['QUOTATION', 'RECEIPT', 'HANDOVER', 'RETURN', 'DISPOSAL_EVIDENCE', 'OTHER'], array_keys(Attachment::CATEGORIES));
        foreach (Attachment::CATEGORIES as $label) {
            $this->assertNotSame('', $label);
        }
    }
}
