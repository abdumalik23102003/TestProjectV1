<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminFlowTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_TID = 700001;

    private const TEACHER_TID = 700002;

    private const STUDENT_TID = 700003;

    private int $updateId = 850000;

    private User $admin;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => 'test-token']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 50]]),
        ]);

        $this->admin = $this->makeUser(self::ADMIN_TID, 'Bosh Admin', 'admin', 'active');
        $this->teacher = $this->makeUser(self::TEACHER_TID, 'Yangi Ustoz', 'teacher', 'pending');
        $this->student = $this->makeUser(self::STUDENT_TID, 'Oddiy Oquvchi', 'student', 'active');
    }

    private function makeUser(int $tid, string $name, string $role, string $status): User
    {
        return User::create([
            'telegram_user_id' => $tid,
            'full_name' => $name,
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function sendCallback(int $fromId, string $data): void
    {
        $this->postJson('/api/telegram/webhook', [
            'update_id' => ++$this->updateId,
            'callback_query' => [
                'id' => 'cb-'.$this->updateId,
                'from' => ['id' => $fromId],
                'data' => $data,
                'message' => ['message_id' => 50, 'chat' => ['id' => $fromId]],
            ],
        ])->assertOk();
    }

    private function sendText(int $fromId, string $text): void
    {
        $this->postJson('/api/telegram/webhook', [
            'update_id' => ++$this->updateId,
            'message' => [
                'message_id' => $this->updateId,
                'chat' => ['id' => $fromId],
                'from' => ['id' => $fromId],
                'text' => $text,
            ],
        ])->assertOk();
    }

    private function assertSentTo(int $chatId, string $text): void
    {
        Http::assertSent(function ($request) use ($chatId, $text) {
            $data = $request->data();

            return (int) ($data['chat_id'] ?? 0) === $chatId && ($data['text'] ?? null) === $text;
        });
    }

    public function test_admin_approves_pending_teacher(): void
    {
        $this->sendCallback(self::ADMIN_TID, 'admin:approve_teacher:'.$this->teacher->id);

        $teacher = $this->teacher->fresh();
        $this->assertSame('active', $teacher->status);
        $this->assertNotNull($teacher->teacher_approved_at);

        $log = AuditLog::where('action_type', 'teacher_approved')->firstOrFail();
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($this->teacher->id, $log->subject_id);

        $this->assertSentTo(self::TEACHER_TID, __('bot.notice_teacher_approved'));
    }

    public function test_admin_rejects_pending_teacher(): void
    {
        $this->sendCallback(self::ADMIN_TID, 'admin:reject_teacher:'.$this->teacher->id);

        $this->assertSame('rejected', $this->teacher->fresh()->status);
        $this->assertTrue(AuditLog::where('action_type', 'teacher_rejected')->exists());
        $this->assertSentTo(self::TEACHER_TID, __('bot.notice_teacher_rejected'));
    }

    public function test_non_admin_cannot_use_admin_callbacks(): void
    {
        $this->sendCallback(self::STUDENT_TID, 'admin:approve_teacher:'.$this->teacher->id);
        $this->sendCallback(self::STUDENT_TID, 'admin:user_block_do:'.$this->teacher->id);

        $this->assertSame('pending', $this->teacher->fresh()->status);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_admin_cannot_block_self_or_another_admin(): void
    {
        $other = $this->makeUser(700009, 'Boshqa Admin', 'admin', 'active');

        $this->sendCallback(self::ADMIN_TID, 'admin:user_block_do:'.$this->admin->id);
        $this->sendCallback(self::ADMIN_TID, 'admin:user_block_do:'.$other->id);

        $this->assertSame('active', $this->admin->fresh()->status);
        $this->assertSame('active', $other->fresh()->status);
        $this->assertFalse(AuditLog::where('action_type', 'user_blocked')->exists());
    }

    public function test_block_requires_confirmation_then_blocks_notifies_and_clears_sessions(): void
    {
        SessionService::startWizard(self::STUDENT_TID, 'search', 'enter_query');

        $this->sendCallback(self::ADMIN_TID, 'admin:user_block:'.$this->student->id);
        $this->assertSame('active', $this->student->fresh()->status, 'Tasdiqsiz bloklanmasligi kerak');

        $this->sendCallback(self::ADMIN_TID, 'admin:user_block_do:'.$this->student->id);

        $this->assertSame('blocked', $this->student->fresh()->status);
        $this->assertTrue(AuditLog::where('action_type', 'user_blocked')->where('actor_id', $this->admin->id)->exists());
        $this->assertSentTo(self::STUDENT_TID, __('bot.blocked_user'));
        $this->assertNull(SessionService::getSession(self::STUDENT_TID, 'search'));
    }

    public function test_blocked_user_is_stopped_for_text_and_callbacks(): void
    {
        $this->student->update(['status' => 'blocked']);

        $this->sendText(self::STUDENT_TID, 'salom');
        $this->assertSentTo(self::STUDENT_TID, __('bot.blocked_user'));

        $this->sendCallback(self::STUDENT_TID, 'student:search');
        $this->assertNull(SessionService::getSession(self::STUDENT_TID, 'search'));
    }

    public function test_unblock_restores_status_correctly(): void
    {
        $this->student->update(['status' => 'blocked']);
        // Hech qachon tasdiqlanmagan ustoz bloklangan edi
        $this->teacher->update(['status' => 'blocked']);

        $this->sendCallback(self::ADMIN_TID, 'admin:user_unblock:'.$this->student->id);
        $this->sendCallback(self::ADMIN_TID, 'admin:user_unblock:'.$this->teacher->id);

        $this->assertSame('active', $this->student->fresh()->status);
        $this->assertSame('pending', $this->teacher->fresh()->status);
        $this->assertSame(2, AuditLog::where('action_type', 'user_unblocked')->count());
        $this->assertSentTo(self::STUDENT_TID, __('bot.notice_user_unblocked'));
    }

    public function test_audit_metadata_is_stored_as_json_object_not_double_encoded(): void
    {
        $log = app(AuditService::class)->logTeacherApproved($this->admin, $this->teacher);

        $this->assertIsArray(AuditLog::find($log->id)->metadata);
        $this->assertSame('Yangi Ustoz', AuditLog::find($log->id)->metadata['teacher_name']);

        $raw = DB::table('audit_logs')->where('id', $log->id)->value('metadata');
        $this->assertIsArray(json_decode($raw, true), 'metadata ikki marta JSON bo‘lib qolgan');
    }
}
