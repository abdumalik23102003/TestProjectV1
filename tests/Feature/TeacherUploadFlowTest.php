<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherUploadFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TEACHER_ID = 555001;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => 'test-token']);

        User::create([
            'telegram_user_id' => self::TEACHER_ID,
            'full_name' => 'Test Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);
    }

    private function fakeTelegram(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

        Http::fake([
            'api.telegram.org/file/*' => Http::response($pdf),
            'api.telegram.org/bot*/getFile' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'documents/file_1.pdf'],
            ]),
            'api.telegram.org/bot*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    private function assertMessageSent(string $needle): void
    {
        Http::assertSent(function ($request) use ($needle) {
            return str_contains($request->url(), '/sendMessage')
                && str_contains((string) ($request['text'] ?? ''), $needle);
        });
    }

    public function test_teacher_gets_replies_after_sending_a_file(): void
    {
        Storage::fake('private_materials');
        $this->fakeTelegram();

        SessionService::startWizard(self::TEACHER_ID, 'upload', 'awaiting_file');

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 710001,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => self::TEACHER_ID],
                'from' => ['id' => self::TEACHER_ID],
                'document' => ['file_id' => 'abc', 'file_name' => 'lesson.pdf'],
            ],
        ])->assertOk();

        // 1) Fayl qabul qilingani haqida darhol javob
        $this->assertMessageSent(__('bot.file_downloading'));

        // 2) Metadata bosqichi: sarlavha so'raladi
        $this->assertMessageSent('sarlavha');

        $session = SessionService::getSession(self::TEACHER_ID, 'upload');
        $this->assertSame('enter_title', $session['current_step']);
    }

    public function test_confirmation_message_has_valid_buttons(): void
    {
        SessionService::startWizard(self::TEACHER_ID, 'upload', 'enter_visibility', [
            'file_path' => 'x/lesson.pdf',
            'original_name' => 'lesson.pdf',
            'title' => 'Dars',
            'description' => null,
        ]);

        $this->fakeTelegram();

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 710002,
            'message' => [
                'message_id' => 2,
                'chat' => ['id' => self::TEACHER_ID],
                'from' => ['id' => self::TEACHER_ID],
                'text' => 'public',
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/sendMessage')) {
                return false;
            }

            $markup = json_decode($request['reply_markup'] ?? 'null', true);

            return str_contains((string) ($request['text'] ?? ''), 'Tasdiqlang')
                && isset($markup['inline_keyboard'][0][0]['text']);
        });
    }

    public function test_text_instead_of_file_gets_a_hint(): void
    {
        $this->fakeTelegram();

        SessionService::startWizard(self::TEACHER_ID, 'upload', 'awaiting_file');

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 710003,
            'message' => [
                'message_id' => 3,
                'chat' => ['id' => self::TEACHER_ID],
                'from' => ['id' => self::TEACHER_ID],
                'text' => 'salom',
            ],
        ])->assertOk();

        $this->assertMessageSent(__('bot.awaiting_file_hint'));
    }
}
