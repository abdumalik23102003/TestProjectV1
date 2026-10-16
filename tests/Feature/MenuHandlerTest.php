<?php

namespace Tests\Feature;

use App\Models\User;
use App\Telegram\Menus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menu_structure(): void
    {
        $menu = Menus::adminMain();

        $this->assertArrayHasKey('text', $menu);
        $this->assertArrayHasKey('buttons', $menu);
        $this->assertNotEmpty($menu['buttons']);

        // Check button structure
        foreach ($menu['buttons'] as $row) {
            foreach ($row as $btn) {
                $this->assertArrayHasKey('text', $btn);
                $this->assertArrayHasKey('callback_data', $btn);
            }
        }
    }

    public function test_teacher_menu_structure(): void
    {
        $menu = Menus::teacherMain();

        $this->assertArrayHasKey('text', $menu);
        $this->assertArrayHasKey('buttons', $menu);
        $this->assertNotEmpty($menu['buttons']);
    }

    public function test_student_menu_structure(): void
    {
        $menu = Menus::studentMain();

        $this->assertArrayHasKey('text', $menu);
        $this->assertArrayHasKey('buttons', $menu);
        $this->assertNotEmpty($menu['buttons']);
    }

    public function test_menu_main_selects_role_menu(): void
    {
        $admin = User::create([
            'telegram_user_id' => 123,
            'full_name' => 'Admin User',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $menu = Menus::main($admin);
        $this->assertStringContainsString('Admin', $menu['text']);

        $teacher = User::create([
            'telegram_user_id' => 124,
            'full_name' => 'Teacher User',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $menu = Menus::main($teacher);
        $this->assertStringContainsString('Ustoz', $menu['text']);

        $student = User::create([
            'telegram_user_id' => 125,
            'full_name' => 'Student User',
            'role' => 'student',
            'status' => 'active',
        ]);
        $menu = Menus::main($student);
        $this->assertStringContainsString('O\'quvchi', $menu['text']);
    }

    public function test_confirm_menu_structure(): void
    {
        $menu = Menus::confirm('Test prompt');

        $this->assertArrayHasKey('text', $menu);
        $this->assertArrayHasKey('buttons', $menu);
        $this->assertEquals('Test prompt', $menu['text']);

        // Check for confirm/cancel buttons
        $found_confirm = false;
        foreach ($menu['buttons'][0] as $btn) {
            if (str_contains($btn['callback_data'], 'confirm:')) {
                $found_confirm = true;
            }
        }
        $this->assertTrue($found_confirm);
    }

    public function test_category_select_menu(): void
    {
        $menu = Menus::categorySelect();

        $this->assertArrayHasKey('text', $menu);
        $this->assertArrayHasKey('buttons', $menu);
        $this->assertNotEmpty($menu['buttons']);

        // Should have skip and back buttons
        $last_btn = end($menu['buttons'])[0];
        $this->assertArrayHasKey('callback_data', $last_btn);
    }

    public function test_visibility_select_menu(): void
    {
        $menu = Menus::visibilitySelect();

        $this->assertArrayHasKey('text', $menu);
        $this->assertArrayHasKey('buttons', $menu);

        // Should have public and private options
        $buttons_text = json_encode($menu['buttons']);
        $this->assertStringContainsString('public', $buttons_text);
        $this->assertStringContainsString('private', $buttons_text);
    }

    public function test_file_actions_menu(): void
    {
        $fileId = 'test-file-123';
        $menu = Menus::fileActions($fileId);

        $this->assertArrayHasKey('text', $menu);
        $this->assertArrayHasKey('buttons', $menu);

        // Check file ID is included in callback data
        $buttons_text = json_encode($menu['buttons']);
        $this->assertStringContainsString($fileId, $buttons_text);
    }
}
