<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\TeacherStudent;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StudentFlowTest extends TestCase
{
    use RefreshDatabase;

    private const STUDENT_ID = 777001;

    private const TEACHER_ID = 777002;

    private User $student;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        config(['telegram.bot_token' => 'test-token']);

        $this->student = User::create([
            'telegram_user_id' => self::STUDENT_ID,
            'full_name' => 'Test Oquvchi',
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->teacher = User::create([
            'telegram_user_id' => self::TEACHER_ID,
            'full_name' => 'Test Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);
    }

    private function makeMaterial(string $visibility, string $title = 'Matematika darsi'): Material
    {
        return Material::create([
            'teacher_id' => $this->teacher->id,
            'original_filename' => 'lesson.pdf',
            'title' => $title,
            'visibility' => $visibility,
            'status' => 'active',
            'storage_disk' => 'private_materials',
            'storage_path' => 'x/lesson.pdf',
            'size_bytes' => 2048,
            'mime_type' => 'application/pdf',
            'checksum' => str_repeat('a', 64),
        ]);
    }

    private function fakeTelegram(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    public function test_private_material_requires_active_connection(): void
    {
        $material = $this->makeMaterial('private');
        $permissions = app(PermissionService::class);

        $this->assertFalse($permissions->canViewMaterial($this->student, $material));

        $connection = TeacherStudent::create([
            'teacher_id' => $this->teacher->id,
            'student_id' => $this->student->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->assertFalse($permissions->canViewMaterial($this->student, $material));

        $connection->update(['status' => 'active', 'approved_at' => now()]);

        $this->assertTrue($permissions->canViewMaterial($this->student, $material));
        $this->assertTrue($permissions->canViewMaterial($this->teacher, $material));
    }

    public function test_student_search_shows_results_with_file_size(): void
    {
        $this->makeMaterial('public');
        $this->fakeTelegram();

        SessionService::startWizard(self::STUDENT_ID, 'search', 'enter_query');

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 810001,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => self::STUDENT_ID],
                'from' => ['id' => self::STUDENT_ID],
                'text' => 'Matematika',
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            $text = (string) ($request['text'] ?? '');

            return str_contains($request->url(), '/sendMessage')
                && str_contains($text, 'Matematika darsi')
                && str_contains($text, '2 KB');
        });
    }

    public function test_student_profile_button_works(): void
    {
        $this->fakeTelegram();

        $this->postJson('/api/telegram/webhook', [
            'update_id' => 810002,
            'callback_query' => [
                'id' => 'cb-1',
                'from' => ['id' => self::STUDENT_ID],
                'data' => 'menu:profile',
                'message' => [
                    'message_id' => 5,
                    'chat' => ['id' => self::STUDENT_ID],
                ],
            ],
        ])->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/editMessageText')
                && str_contains((string) ($request['text'] ?? ''), 'Profil')
                && str_contains((string) ($request['text'] ?? ''), 'Ustozlar');
        });
    }
}
