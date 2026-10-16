<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadWizardFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TEACHER_ID = 910001;

    private int $updateId = 830000;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => 'test-token']);
        Storage::fake('private_materials');

        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        Http::fake([
            'api.telegram.org/file/*' => Http::response($pdf),
            'api.telegram.org/bot*/getFile' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'documents/file_1.pdf'],
            ]),
            'api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => ['message_id' => 50]]),
        ]);
    }

    private function sendTestPost(array $update): void
    {
        $this->postJson('/api/telegram/webhook', ['update_id' => ++$this->updateId] + $update)->assertOk();
    }

    private function sendText(string $text): void
    {
        $this->sendTestPost(['message' => [
            'message_id' => $this->updateId,
            'chat' => ['id' => self::TEACHER_ID],
            'from' => ['id' => self::TEACHER_ID],
            'text' => $text,
        ]]);
    }

    private function sendCallback(string $data): void
    {
        $this->sendTestPost(['callback_query' => [
            'id' => 'cb-'.$this->updateId,
            'from' => ['id' => self::TEACHER_ID],
            'data' => $data,
            'message' => ['message_id' => 50, 'chat' => ['id' => self::TEACHER_ID]],
        ]]);
    }

    public function test_teacher_uploads_file_through_whole_wizard(): void
    {
        $teacher = User::create([
            'telegram_user_id' => self::TEACHER_ID,
            'full_name' => 'Test Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        SessionService::startWizard(self::TEACHER_ID, 'upload', 'awaiting_file');

        $this->sendTestPost(['message' => [
            'message_id' => 1,
            'chat' => ['id' => self::TEACHER_ID],
            'from' => ['id' => self::TEACHER_ID],
            'document' => ['file_id' => 'abc', 'file_name' => 'Дарс.pdf'],
        ]]);

        $this->sendText('-');              // sarlavha: fayl nomidan
        $this->sendText('-');              // tavsif yo'q
        $this->sendCallback('upload:cat:none');
        $this->sendCallback('upload:vis:private');
        $this->sendCallback('upload:save');

        $material = Material::firstOrFail();

        $this->assertSame($teacher->id, $material->teacher_id);
        $this->assertSame('Дарс.pdf', $material->original_filename);
        $this->assertSame('Дарс', $material->title);
        $this->assertSame('private', $material->visibility);
        $this->assertSame('active', $material->status);
        $this->assertNotNull($material->current_version_id);
        $this->assertSame(1, $material->versions()->count());

        $disk = Storage::disk('private_materials');
        $disk->assertExists($material->storage_path);

        $leftovers = collect($disk->allFiles())->filter(fn ($f) => str_contains($f, 'pending_'));
        $this->assertTrue($leftovers->isEmpty(), 'Vaqtinchalik fayl qolib ketdi');

        $this->assertNull(SessionService::getSession(self::TEACHER_ID, 'upload'));
    }

    public function test_too_long_title_is_rejected_and_wizard_stays_on_step(): void
    {
        User::create([
            'telegram_user_id' => self::TEACHER_ID,
            'full_name' => 'Test Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        SessionService::startWizard(self::TEACHER_ID, 'upload', 'enter_title', [
            'file_path' => 'x/pending_a.pdf',
            'original_name' => 'a.pdf',
        ]);

        $this->sendText(str_repeat('A', 300));

        $this->assertSame('enter_title', SessionService::getSession(self::TEACHER_ID, 'upload')['current_step']);
    }
}
