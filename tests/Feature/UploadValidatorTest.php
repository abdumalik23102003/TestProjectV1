<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UploadValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadValidatorTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    /** @var string[] */
    private array $tmp = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'telegram_user_id' => 900001,
            'full_name' => 'Test Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function tmpFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upl_');
        file_put_contents($path, $content);
        $this->tmp[] = $path;

        return $path;
    }

    private function validate(string $path, string $name): array
    {
        return app(UploadValidator::class)->validate($path, $this->teacher, $name);
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    public function test_pdf_is_accepted(): void
    {
        $result = $this->validate($this->tmpFile($this->pdf()), 'lesson.pdf');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('application/pdf', $result['mime']);
        $this->assertSame(64, strlen($result['checksum']));
    }

    public function test_rtf_is_accepted(): void
    {
        $result = $this->validate($this->tmpFile('{\rtf1\ansi\deff0 {\fonttbl {\f0 Times;}} Salom}'), 'notes.rtf');

        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_docx_zip_is_accepted(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive mavjud emas.');
        }

        $path = $this->tmpFile('');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString('word/document.xml', '<w/>');
        $zip->close();

        $this->assertArrayNotHasKey('error', $this->validate($path, 'Mening hujjatim.docx'));
    }

    public function test_plain_text_renamed_to_docx_is_rejected(): void
    {
        $this->assertArrayHasKey('error', $this->validate($this->tmpFile('oddiy matn'), 'fake.docx'));
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $this->assertArrayHasKey('error', $this->validate($this->tmpFile('MZ binary'), 'virus.exe'));
    }

    public function test_content_not_matching_extension_is_rejected(): void
    {
        $this->assertArrayHasKey('error', $this->validate($this->tmpFile('bu pdf emas'), 'fake.pdf'));
    }

    public function test_too_large_file_is_rejected(): void
    {
        config(['materials.max_file_size_mb' => 1]);

        $result = $this->validate($this->tmpFile($this->pdf().str_repeat('0', 2 * 1024 * 1024)), 'big.pdf');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('1 MB', $result['error']);
    }
}
