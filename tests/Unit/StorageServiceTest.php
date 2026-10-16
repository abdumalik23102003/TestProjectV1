<?php

namespace Tests\Unit;

use App\Services\StorageService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageServiceTest extends TestCase
{
    public function test_sanitize_keeps_unicode_letters(): void
    {
        $service = new StorageService;

        $this->assertSame('Дарс 1.pdf', $service->sanitizeFilename('Дарс 1.pdf'));
        $this->assertSame('Oʻzbek_tili.docx', $service->sanitizeFilename('Oʻzbek_tili.docx'));
    }

    public function test_sanitize_strips_paths_and_dangerous_characters(): void
    {
        $service = new StorageService;

        $this->assertSame('passwd', $service->sanitizeFilename('../../etc/passwd'));
        $this->assertSame('a_b_.pdf', $service->sanitizeFilename('a<b>.pdf'));
        $this->assertSame('a.pdf', $service->sanitizeFilename('a..pdf'));
        $this->assertSame('document', $service->sanitizeFilename('///'));
    }

    public function test_temporary_and_permanent_paths_use_uuid_only(): void
    {
        Storage::fake('private_materials');

        $source = tempnam(sys_get_temp_dir(), 'src_');
        file_put_contents($source, 'hello');

        $service = new StorageService;
        $stored = $service->storeTemporaryFile($source, 'Дарс.pdf');

        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/\d{2}/pending_[0-9a-f-]{36}\.pdf$#', $stored['path']);
        $this->assertSame('Дарс.pdf', $stored['original_name']);
        $this->assertSame(hash('sha256', 'hello'), $stored['checksum']);

        $permanent = $service->promoteToPermanent($stored['path']);

        $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/\d{2}/[0-9a-f-]{36}\.pdf$#', $permanent);
        Storage::disk('private_materials')->assertExists($permanent);
        Storage::disk('private_materials')->assertExists($stored['path']);

        @unlink($source);
    }
}
