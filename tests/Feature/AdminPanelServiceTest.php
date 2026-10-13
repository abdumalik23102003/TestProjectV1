<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminPanelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdminPanelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AdminPanelService::class);
    }

    public function test_get_pending_teachers(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Teacher 1',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        User::create([
            'telegram_user_id' => 2,
            'full_name' => 'Teacher 2',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        User::create([
            'telegram_user_id' => 3,
            'full_name' => 'Active Teacher',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $pending = $this->service->getPendingTeachers();

        $this->assertCount(2, $pending);
    }

    public function test_get_pending_teachers_count(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Teacher 1',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        User::create([
            'telegram_user_id' => 2,
            'full_name' => 'Teacher 2',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        $count = $this->service->getPendingTeachersCount();

        $this->assertEquals(2, $count);
    }

    public function test_get_active_users(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Active User',
            'role' => 'student',
            'status' => 'active',
        ]);

        User::create([
            'telegram_user_id' => 2,
            'full_name' => 'Pending User',
            'role' => 'student',
            'status' => 'pending',
        ]);

        $active = $this->service->getActiveUsers();

        $this->assertCount(1, $active);
    }

    public function test_get_blocked_users(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Blocked User',
            'role' => 'student',
            'status' => 'blocked',
        ]);

        User::create([
            'telegram_user_id' => 2,
            'full_name' => 'Active User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $blocked = $this->service->getBlockedUsers();

        $this->assertCount(1, $blocked);
    }

    public function test_get_users_by_role(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Teacher',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        User::create([
            'telegram_user_id' => 2,
            'full_name' => 'Teacher 2',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        User::create([
            'telegram_user_id' => 3,
            'full_name' => 'Student',
            'role' => 'student',
            'status' => 'active',
        ]);

        $teachers = $this->service->getUsersByRole('teacher');

        $this->assertCount(2, $teachers);
    }

    public function test_get_user_stats(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);

        User::create([
            'telegram_user_id' => 2,
            'full_name' => 'Teacher',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        User::create([
            'telegram_user_id' => 3,
            'full_name' => 'Student',
            'role' => 'student',
            'status' => 'active',
        ]);

        $stats = $this->service->getUserStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('blocked', $stats);
        $this->assertArrayHasKey('teachers', $stats);
        $this->assertArrayHasKey('students', $stats);
        $this->assertArrayHasKey('admins', $stats);
        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        $this->assertEquals(1, $stats['pending']);
        $this->assertEquals(1, $stats['teachers']);
        $this->assertEquals(1, $stats['students']);
        $this->assertEquals(1, $stats['admins']);
    }

    public function test_block_user(): void
    {
        $user = User::create([
            'telegram_user_id' => 1,
            'full_name' => 'User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $blocked = $this->service->blockUser($user);

        $this->assertEquals('blocked', $blocked->status);
        $this->assertEquals('blocked', $user->fresh()->status);
    }

    public function test_unblock_user(): void
    {
        $user = User::create([
            'telegram_user_id' => 1,
            'full_name' => 'User',
            'role' => 'student',
            'status' => 'blocked',
        ]);

        $unblocked = $this->service->unblockUser($user);

        $this->assertEquals('active', $unblocked->status);
        $this->assertEquals('active', $user->fresh()->status);
    }

    public function test_approve_teacher(): void
    {
        $teacher = User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Teacher',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        $approved = $this->service->approveTeacher($teacher);

        $this->assertEquals('active', $approved->status);
        $this->assertNotNull($approved->teacher_approved_at);
    }

    public function test_reject_teacher(): void
    {
        $teacher = User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Teacher',
            'role' => 'teacher',
            'status' => 'pending',
        ]);

        $rejected = $this->service->rejectTeacher($teacher, 'Invalid documents');

        $this->assertEquals('rejected', $rejected->status);
    }

    public function test_get_user_by_id(): void
    {
        $user = User::create([
            'telegram_user_id' => 1,
            'full_name' => 'User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $found = $this->service->getUserById($user->id);

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_get_user_by_telegram_id(): void
    {
        $user = User::create([
            'telegram_user_id' => 12345,
            'full_name' => 'User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $found = $this->service->getUserByTelegramId(12345);

        $this->assertNotNull($found);
        $this->assertEquals($user->id, $found->id);
    }

    public function test_search_users(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'John Smith',
            'role' => 'student',
            'status' => 'active',
        ]);

        User::create([
            'telegram_user_id' => 2,
            'full_name' => 'Jane Doe',
            'role' => 'student',
            'status' => 'active',
        ]);

        $results = $this->service->searchUsers('John');

        $this->assertCount(1, $results);
        $this->assertEquals('John Smith', $results->first()->full_name);
    }

    public function test_format_user_for_display(): void
    {
        $user = User::create([
            'telegram_user_id' => 1,
            'full_name' => 'Test User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $formatted = $this->service->formatUserForDisplay($user);

        $this->assertStringContainsString('Test User', $formatted);
        $this->assertStringContainsString('student', $formatted);
        $this->assertStringContainsString($user->id, $formatted);
    }

    public function test_get_system_stats(): void
    {
        User::create([
            'telegram_user_id' => 1,
            'full_name' => 'User',
            'role' => 'student',
            'status' => 'active',
        ]);

        $stats = $this->service->getSystemStats();

        $this->assertArrayHasKey('users', $stats);
        $this->assertArrayHasKey('audit_actions_7days', $stats);
        $this->assertArrayHasKey('total_materials', $stats);
        $this->assertArrayHasKey('total_audit_logs', $stats);
    }
}
