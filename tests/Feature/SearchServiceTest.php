<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\TeacherStudent;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private SearchService $service;

    private User $teacher;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SearchService::class);
        $this->teacher = $this->makeUser(720001, 'teacher', 'active');
        $this->student = $this->makeUser(720002, 'student', 'active');
    }

    private function makeUser(int $tid, string $role, string $status): User
    {
        return User::create([
            'telegram_user_id' => $tid,
            'full_name' => "User {$tid}",
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function makeMaterial(User $teacher, string $title, string $visibility = 'public', array $extra = []): Material
    {
        return Material::create(array_merge([
            'teacher_id' => $teacher->id,
            'original_filename' => 'file_'.uniqid().'.pdf',
            'title' => $title,
            'visibility' => $visibility,
            'status' => 'active',
            'storage_disk' => 'private_materials',
            'storage_path' => 'x/'.uniqid().'.pdf',
            'size_bytes' => 1,
            'mime_type' => 'application/pdf',
            'checksum' => str_repeat('a', 64),
        ], $extra));
    }

    public function test_finds_by_title_description_and_filename(): void
    {
        $this->makeMaterial($this->teacher, 'Algebra asoslari');
        $this->makeMaterial($this->teacher, 'Boshqa', 'public', ['description' => 'Geometriya haqida']);
        $this->makeMaterial($this->teacher, 'Uchinchi', 'public', ['original_filename' => 'fizika_darsi.pdf']);

        $this->assertSame(1, $this->service->search($this->student, 'Algebra')->total());
        $this->assertSame(1, $this->service->search($this->student, 'Geometriya')->total());
        $this->assertSame(1, $this->service->search($this->student, 'fizika')->total());
    }

    public function test_percent_and_underscore_are_literal(): void
    {
        $this->makeMaterial($this->teacher, '100% tayyor');
        $this->makeMaterial($this->teacher, 'a_b');
        $this->makeMaterial($this->teacher, 'axb');

        $this->assertSame(1, $this->service->search($this->student, '%')->total());
        $this->assertSame(1, $this->service->search($this->student, 'a_b')->total());
    }

    public function test_materials_of_inactive_teachers_are_hidden(): void
    {
        $blocked = $this->makeUser(720003, 'teacher', 'blocked');
        $pending = $this->makeUser(720004, 'teacher', 'pending');

        $this->makeMaterial($blocked, 'Yashirin bir');
        $this->makeMaterial($pending, 'Yashirin ikki');

        $this->assertSame(0, $this->service->search($this->student, 'Yashirin')->total());
        $this->assertCount(0, $this->service->searchForTeacher($this->teacher, 'Yashirin', 'shared'));
    }

    public function test_private_material_needs_active_connection(): void
    {
        $this->makeMaterial($this->teacher, 'Maxfiy konspekt', 'private');

        $this->assertSame(0, $this->service->search($this->student, 'Maxfiy')->total());

        $link = TeacherStudent::create([
            'teacher_id' => $this->teacher->id,
            'student_id' => $this->student->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        $this->assertSame(0, $this->service->search($this->student, 'Maxfiy')->total());

        $link->update(['status' => 'active', 'approved_at' => now()]);

        $this->assertSame(1, $this->service->search($this->student, 'Maxfiy')->total());
    }

    public function test_teacher_scopes(): void
    {
        $other = $this->makeUser(720005, 'teacher', 'active');

        $this->makeMaterial($this->teacher, 'Mening ochiq darsim');
        $this->makeMaterial($this->teacher, 'Mening shaxsiy darsim', 'private');
        $this->makeMaterial($other, 'Boshqaning ochiq darsi');
        $this->makeMaterial($other, 'Boshqaning shaxsiy darsi', 'private');

        $mine = $this->service->searchForTeacher($this->teacher, 'dars', 'my');
        $shared = $this->service->searchForTeacher($this->teacher, 'dars', 'shared');

        $this->assertCount(2, $mine);
        $this->assertCount(2, $shared);
        $this->assertTrue($shared->every(fn ($m) => $m->visibility === 'public'));
    }
}
