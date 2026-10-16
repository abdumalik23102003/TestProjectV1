<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_user_is_active_student(): void
    {
        $user = User::factory()->create();

        $this->assertSame('student', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNotEmpty($user->full_name);
    }

    public function test_states_and_unique_telegram_ids(): void
    {
        $teacher = User::factory()->teacher()->pending()->create();
        $admin = User::factory()->admin()->create();
        $blocked = User::factory()->blocked()->create();

        $this->assertSame(['teacher', 'pending'], [$teacher->role, $teacher->status]);
        $this->assertSame('admin', $admin->role);
        $this->assertSame('blocked', $blocked->status);
        $this->assertCount(3, collect([$teacher, $admin, $blocked])->pluck('telegram_user_id')->unique());
    }
}
