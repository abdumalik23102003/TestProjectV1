<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupMaterialsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_removes_only_stale_pending_files(): void
    {
        Storage::fake('private_materials');
        $disk = Storage::disk('private_materials');

        $disk->put('2026/10/01/pending_old.pdf', 'x');
        $disk->put('2026/10/01/pending_new.pdf', 'x');
        $disk->put('2026/10/01/keep.pdf', 'x');

        touch($disk->path('2026/10/01/pending_old.pdf'), time() - 3 * 86400);

        $this->artisan('materials:cleanup')->assertSuccessful();

        $disk->assertMissing('2026/10/01/pending_old.pdf');
        $disk->assertExists('2026/10/01/pending_new.pdf');
        $disk->assertExists('2026/10/01/keep.pdf');
    }

    public function test_purges_old_archived_materials_with_files(): void
    {
        Storage::fake('private_materials');
        $disk = Storage::disk('private_materials');

        $teacher = User::create([
            'telegram_user_id' => 920001,
            'full_name' => 'Test Ustoz',
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $disk->put('2026/09/01/old.pdf', 'x');
        $disk->put('2026/10/01/fresh.pdf', 'x');

        $make = fn (string $path, string $status) => Material::create([
            'teacher_id' => $teacher->id,
            'original_filename' => basename($path),
            'title' => basename($path),
            'visibility' => 'public',
            'status' => $status,
            'storage_disk' => 'private_materials',
            'storage_path' => $path,
            'size_bytes' => 1,
            'mime_type' => 'application/pdf',
            'checksum' => str_repeat('a', 64),
        ]);

        $old = $make('2026/09/01/old.pdf', 'archived');
        $fresh = $make('2026/10/01/fresh.pdf', 'archived');

        MaterialVersion::create([
            'material_id' => $old->id,
            'version_number' => 1,
            'storage_path' => '2026/09/01/old.pdf',
            'size_bytes' => 1,
            'checksum' => str_repeat('a', 64),
            'uploaded_at' => now(),
            'status' => 'active',
        ]);

        Material::where('id', $old->id)->update(['updated_at' => now()->subDays(40)]);

        $this->artisan('materials:cleanup')->assertSuccessful();

        $this->assertNull(Material::find($old->id));
        $this->assertNotNull(Material::find($fresh->id));
        $disk->assertMissing('2026/09/01/old.pdf');
        $disk->assertExists('2026/10/01/fresh.pdf');
    }
}
