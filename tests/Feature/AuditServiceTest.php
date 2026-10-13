<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = app(AuditService::class);
    }

    public function test_log_creates_audit_record(): void
    {
        $user = User::create([
            'telegram_user_id' => 123,
            'full_name' => 'Test User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $log = $this->auditService->log(
            'test_action',
            'test_subject',
            $user->id,
            $user,
            ['key' => 'value']
        );

        $this->assertNotNull($log->id);
        $this->assertEquals('test_action', $log->action_type);
        $this->assertEquals('test_subject', $log->subject_type);
        $this->assertEquals($user->id, $log->subject_id);
        $this->assertEquals($user->id, $log->actor_id);
    }

    public function test_log_registration(): void
    {
        $user = User::create([
            'telegram_user_id' => 456,
            'full_name' => 'New Student',
            'role' => 'student',
            'status' => 'active',
        ]);

        $log = $this->auditService->logRegistration($user, 'student');

        $this->assertEquals('user_registered', $log->action_type);
        $this->assertEquals('user', $log->subject_type);
        $this->assertEquals($user->id, $log->subject_id);
    }

    public function test_log_teacher_approved(): void
    {
        $admin = User::create([
            'telegram_user_id' => 111,
            'full_name' => 'Admin User',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $teacher = User::create([
            'telegram_user_id' => 222,
            'full_name' => 'Teacher User',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        $log = $this->auditService->logTeacherApproved($admin, $teacher);

        $this->assertEquals('teacher_approved', $log->action_type);
        $this->assertEquals($admin->id, $log->actor_id);
        $this->assertEquals($teacher->id, $log->subject_id);
    }

    public function test_log_teacher_rejected(): void
    {
        $admin = User::create([
            'telegram_user_id' => 111,
            'full_name' => 'Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $teacher = User::create([
            'telegram_user_id' => 333,
            'full_name' => 'Bad Teacher',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        $log = $this->auditService->logTeacherRejected($admin, $teacher, 'Invalid documents');

        $this->assertEquals('teacher_rejected', $log->action_type);
        $this->assertNotNull($log->metadata);
    }

    public function test_log_user_blocked(): void
    {
        $admin = User::create([
            'telegram_user_id' => 111,
            'full_name' => 'Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $user = User::create([
            'telegram_user_id' => 444,
            'full_name' => 'Blocked User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $log = $this->auditService->logUserBlocked($admin, $user, 'Spam');

        $this->assertEquals('user_blocked', $log->action_type);
    }

    public function test_log_file_uploaded(): void
    {
        $teacher = User::create([
            'telegram_user_id' => 555,
            'full_name' => 'Teacher',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $log = $this->auditService->logFileUploaded($teacher, 1, 'document.pdf');

        $this->assertEquals('material_uploaded', $log->action_type);
    }

    public function test_log_file_downloaded(): void
    {
        $student = User::create([
            'telegram_user_id' => 666,
            'full_name' => 'Student',
            'role' => 'student',
            'status' => 'active',
        ]);

        $log = $this->auditService->logFileDownloaded($student, 1, 'notes.pdf');

        $this->assertEquals('material_downloaded', $log->action_type);
    }

    public function test_log_material_search(): void
    {
        $student = User::create([
            'telegram_user_id' => 777,
            'full_name' => 'Student',
            'role' => 'student',
            'status' => 'active',
        ]);

        $log = $this->auditService->logMaterialSearch($student, 'matematika', 5);

        $this->assertEquals('material_searched', $log->action_type);
    }

    public function test_get_by_actor(): void
    {
        $user1 = User::create([
            'telegram_user_id' => 888,
            'full_name' => 'User 1',
            'role' => 'student',
            'status' => 'active',
        ]);

        $user2 = User::create([
            'telegram_user_id' => 999,
            'full_name' => 'User 2',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->auditService->logRegistration($user1, 'student');
        $this->auditService->logRegistration($user2, 'student');
        $this->auditService->logMaterialSearch($user1, 'test', 0);

        $logs = $this->auditService->getByActor($user1, 10);

        $this->assertCount(2, $logs);
    }

    public function test_get_by_action(): void
    {
        $user1 = User::create([
            'telegram_user_id' => 1001,
            'full_name' => 'User 1',
            'role' => 'student',
            'status' => 'active',
        ]);

        $user2 = User::create([
            'telegram_user_id' => 1002,
            'full_name' => 'User 2',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->auditService->logRegistration($user1, 'student');
        $this->auditService->logRegistration($user2, 'student');
        $this->auditService->logMaterialSearch($user1, 'test', 0);

        $logs = $this->auditService->getByAction('user_registered', 10);

        $this->assertCount(2, $logs);
    }

    public function test_get_latest(): void
    {
        $user = User::create([
            'telegram_user_id' => 1003,
            'full_name' => 'User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->auditService->logRegistration($user, 'student');
        $this->auditService->logMaterialSearch($user, 'test', 1);
        $this->auditService->logMaterialSearch($user, 'other', 2);

        $logs = $this->auditService->getLatest(10);

        $this->assertCount(3, $logs);
    }

    public function test_get_dashboard_logs(): void
    {
        $user = User::create([
            'telegram_user_id' => 1004,
            'full_name' => 'Dashboard User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->auditService->logRegistration($user, 'student');

        $logs = $this->auditService->getDashboardLogs(5);

        $this->assertNotEmpty($logs);
        $this->assertArrayHasKey('action', $logs[0]);
        $this->assertArrayHasKey('actor', $logs[0]);
    }

    public function test_format_for_display(): void
    {
        $user = User::create([
            'telegram_user_id' => 1005,
            'full_name' => 'Format User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $log = $this->auditService->logRegistration($user, 'student');
        $formatted = $this->auditService->formatForDisplay($log);

        $this->assertIsString($formatted);
    }

    public function test_search_logs(): void
    {
        $user1 = User::create([
            'telegram_user_id' => 1006,
            'full_name' => 'Alice Smith',
            'role' => 'student',
            'status' => 'active',
        ]);

        $user2 = User::create([
            'telegram_user_id' => 1007,
            'full_name' => 'Bob Jones',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->auditService->logRegistration($user1, 'student');
        $this->auditService->logRegistration($user2, 'student');

        $results = $this->auditService->search('Alice', 10);

        $this->assertCount(1, $results);
    }

    public function test_get_stats(): void
    {
        $user = User::create([
            'telegram_user_id' => 1008,
            'full_name' => 'Stats User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->auditService->logRegistration($user, 'student');
        $this->auditService->logRegistration($user, 'student');
        $this->auditService->logMaterialSearch($user, 'test', 0);

        $stats = $this->auditService->getStats(7);

        $this->assertArrayHasKey('user_registered', $stats);
        $this->assertEquals(2, $stats['user_registered']);
    }

    public function test_clean_old_logs(): void
    {
        $user = User::create([
            'telegram_user_id' => 1009,
            'full_name' => 'Cleanup User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $log = $this->auditService->logRegistration($user, 'student');

        // Simulate old log
        AuditLog::where('id', $log->id)->update(['created_at' => now()->subDays(100)]);

        $deleted = $this->auditService->cleanOldLogs(90);

        $this->assertEquals(1, $deleted);
    }

    public function test_audit_log_with_null_actor(): void
    {
        $log = $this->auditService->log(
            'system_action',
            'system',
            null,
            null,
            ['system' => 'maintenance']
        );

        $this->assertNull($log->actor_id);
        $this->assertEquals('system_action', $log->action_type);
    }
}
