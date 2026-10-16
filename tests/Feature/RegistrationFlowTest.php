<?php

namespace Tests\Feature;

use App\Models\TeacherStudent;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_ID = 880001;

    private const TEACHER_ID = 880002;

    private const STUDENT_ID = 880003;

    private const ADMIN_ID = 999001;

    private const TOKEN = 'invitetoken1234567890';

    private int $updateId = 820000;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telegram.bot_token' => 'test-token',
            'telegram.admin_telegram_ids' => (string) self::ADMIN_ID,
        ]);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    private function sendText(int $userId, string $text): void
    {
        $this->postJson('/api/telegram/webhook', [
            'update_id' => ++$this->updateId,
            'message' => [
                'message_id' => $this->updateId,
                'chat' => ['id' => $userId],
                'from' => ['id' => $userId],
                'text' => $text,
            ],
        ])->assertOk();
    }

    private function sendCallback(int $userId, string $data): void
    {
        $this->postJson('/api/telegram/webhook', [
            'update_id' => ++$this->updateId,
            'callback_query' => [
                'id' => 'cb-'.$this->updateId,
                'from' => ['id' => $userId],
                'data' => $data,
                'message' => [
                    'message_id' => 10,
                    'chat' => ['id' => $userId],
                ],
            ],
        ])->assertOk();
    }

    private function makeTeacher(): User
    {
        return User::create([
            'telegram_user_id' => self::TEACHER_ID,
            'full_name' => 'Test Ustoz',
            'role' => 'teacher',
            'status' => 'active',
            'teacher_approval_token' => self::TOKEN,
        ]);
    }

    private function makeStudent(): User
    {
        return User::create([
            'telegram_user_id' => self::STUDENT_ID,
            'full_name' => 'Test Oquvchi',
            'role' => 'student',
            'status' => 'active',
        ]);
    }

    public function test_student_registers_and_user_is_created_only_after_name(): void
    {
        $this->sendCallback(self::NEW_ID, 'role:student');

        $this->assertSame(0, User::count());
        $this->assertSame('student', SessionService::getSession(self::NEW_ID, 'registration')['role']);

        $this->sendText(self::NEW_ID, 'Ali Valiyev');

        $user = User::where('telegram_user_id', self::NEW_ID)->firstOrFail();
        $this->assertSame('Ali Valiyev', $user->full_name);
        $this->assertSame('student', $user->role);
        $this->assertSame('active', $user->status);
    }

    public function test_teacher_registration_waits_for_approval_and_notifies_admin(): void
    {
        $this->sendCallback(self::NEW_ID, 'role:teacher');
        $this->sendText(self::NEW_ID, 'Dilnoza Karimova');

        $user = User::where('telegram_user_id', self::NEW_ID)->firstOrFail();
        $this->assertSame('teacher', $user->role);
        $this->assertSame('pending', $user->status);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && (int) $request['chat_id'] === self::ADMIN_ID
                && str_contains((string) $request['reply_markup'], 'admin:approve_teachers');
        });
    }

    public function test_invalid_name_is_rejected(): void
    {
        $this->sendCallback(self::NEW_ID, 'role:student');
        $this->sendText(self::NEW_ID, '<b>Ali</b>');

        $this->assertSame(0, User::count());
        $this->assertSame('enter_name', SessionService::getSession(self::NEW_ID, 'registration')['current_step']);
    }

    public function test_blocked_user_cannot_reregister_to_unblock_himself(): void
    {
        $user = User::create([
            'telegram_user_id' => self::NEW_ID,
            'full_name' => 'Bloklangan',
            'role' => 'student',
            'status' => 'blocked',
        ]);

        SessionService::startWizard(self::NEW_ID, 'registration', 'enter_name', ['role' => 'student']);
        $this->sendText(self::NEW_ID, 'Yangi Ism');

        $user->refresh();
        $this->assertSame('blocked', $user->status);
        $this->assertSame('Bloklangan', $user->full_name);

        // Eski tugmani bossa ham o'tmaydi
        $this->sendCallback(self::NEW_ID, 'role:student');
        $this->assertNull(SessionService::getSession(self::NEW_ID, 'registration'));
    }

    public function test_user_with_abandoned_registration_can_start_again(): void
    {
        User::create([
            'telegram_user_id' => self::NEW_ID,
            'full_name' => '?',
            'role' => 'student',
            'status' => 'pending',
        ]);

        $this->sendText(self::NEW_ID, '/start');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && str_contains((string) ($request['reply_markup'] ?? ''), 'role:student');
        });
    }

    public function test_join_link_creates_request_and_teacher_can_approve(): void
    {
        $teacher = $this->makeTeacher();
        $student = $this->makeStudent();

        $this->sendText(self::STUDENT_ID, '/start join_'.self::TOKEN);

        $connection = TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->firstOrFail();
        $this->assertSame('pending', $connection->status);

        // Ustozga tasdiqlash tugmasi bilan xabar boradi
        Http::assertSent(function ($request) use ($connection) {
            return str_contains($request->url(), '/sendMessage')
                && (int) $request['chat_id'] === self::TEACHER_ID
                && str_contains((string) $request['reply_markup'], 'teacher:approve_student:'.$connection->id);
        });

        $this->sendCallback(self::TEACHER_ID, 'teacher:approve_student:'.$connection->id);

        $this->assertSame('active', $connection->fresh()->status);

        // O'quvchiga xabar boradi
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && (int) $request['chat_id'] === self::STUDENT_ID
                && str_contains((string) $request['text'], 'Test Ustoz');
        });
    }

    public function test_new_user_with_join_link_registers_then_requests_connection(): void
    {
        $teacher = $this->makeTeacher();

        $this->sendText(self::NEW_ID, '/start join_'.self::TOKEN);
        $this->assertSame(self::TOKEN, SessionService::getSession(self::NEW_ID, 'registration')['join_token']);

        $this->sendCallback(self::NEW_ID, 'role:student');
        $this->sendText(self::NEW_ID, 'Sardor Aliyev');

        $student = User::where('telegram_user_id', self::NEW_ID)->firstOrFail();

        $this->assertSame(
            'pending',
            TeacherStudent::where('teacher_id', $teacher->id)->where('student_id', $student->id)->value('status')
        );
    }

    public function test_invalid_join_token_is_reported(): void
    {
        $this->makeTeacher();
        $this->makeStudent();

        $this->sendText(self::STUDENT_ID, '/start join_wrongtoken0000000000');

        $this->assertSame(0, TeacherStudent::count());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && (int) $request['chat_id'] === self::STUDENT_ID
                && str_contains((string) $request['text'], 'noto‘g‘ri');
        });
    }
}
