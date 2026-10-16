<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchInputFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TEACHER_TID = 730001;

    private int $updateId = 860000;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => 'test-token']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 50]]),
        ]);

        $this->teacher = User::create([
            'telegram_user_id' => self::TEACHER_TID,
            'full_name' => 'Qidiruvchi Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $other = User::create([
            'telegram_user_id' => 730002,
            'full_name' => 'Boshqa Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $this->material($this->teacher, 'Algebra darsi');
        $this->material($other, 'Algebra ikkinchi');
    }

    private function material(User $teacher, string $title): void
    {
        Material::create([
            'teacher_id' => $teacher->id,
            'original_filename' => $title.'.pdf',
            'title' => $title,
            'visibility' => 'public',
            'status' => 'active',
            'storage_disk' => 'private_materials',
            'storage_path' => 'x/'.uniqid().'.pdf',
            'size_bytes' => 1,
            'mime_type' => 'application/pdf',
            'checksum' => str_repeat('a', 64),
        ]);
    }

    private function sendText(string $text): void
    {
        $this->postJson('/api/telegram/webhook', [
            'update_id' => ++$this->updateId,
            'message' => [
                'message_id' => $this->updateId,
                'chat' => ['id' => self::TEACHER_TID],
                'from' => ['id' => self::TEACHER_TID],
                'text' => $text,
            ],
        ])->assertOk();
    }

    private function assertSentContaining(string $needle, bool $expected = true): void
    {
        $sent = Http::recorded()->contains(
            fn ($pair) => str_contains((string) ($pair[0]->data()['text'] ?? ''), $needle)
        );

        $this->assertSame($expected, $sent);
    }

    public function test_my_scope_shows_only_own_materials_and_clears_session(): void
    {
        SessionService::startWizard(self::TEACHER_TID, 'search', 'enter_query', ['scope' => 'my']);

        $this->sendText('Algebra');

        $this->assertSentContaining('Algebra darsi');
        $this->assertSentContaining('Algebra ikkinchi', false);
        $this->assertNull(SessionService::getSession(self::TEACHER_TID, 'search'));
    }

    public function test_shared_scope_shows_materials_of_all_active_teachers(): void
    {
        SessionService::startWizard(self::TEACHER_TID, 'search', 'enter_query', ['scope' => 'shared']);

        $this->sendText('Algebra');

        $this->assertSentContaining('Algebra darsi');
        $this->assertSentContaining('Algebra ikkinchi');
    }

    public function test_too_short_query_keeps_session(): void
    {
        SessionService::startWizard(self::TEACHER_TID, 'search', 'enter_query', ['scope' => 'my']);

        $this->sendText('a');

        $this->assertSentContaining(__('bot.search_too_short'));
        $this->assertSame('enter_query', SessionService::getSession(self::TEACHER_TID, 'search')['current_step']);
    }
}
