<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\MaterialVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupMaterialsCommand extends Command
{
    protected $signature = 'materials:cleanup
                            {--pending-hours=24 : Shuncha soatdan eski vaqtinchalik fayllar o\'chiriladi}
                            {--archived-days=30 : Shuncha kundan beri arxivdagi materiallar o\'chiriladi}';

    protected $description = 'Yetim vaqtinchalik fayllarni, eskirgan arxiv materiallari va versiyalarini tozalaydi';

    public function handle(): int
    {
        $disk = Storage::disk('private_materials');

        // 1) Tugallanmagan yuklashlardan qolgan "pending_" fayllar
        $cutoff = now()->subHours((int) $this->option('pending-hours'))->getTimestamp();
        $pending = 0;

        foreach ($disk->allFiles() as $path) {
            if (! str_starts_with(basename($path), 'pending_')) {
                continue;
            }

            if ($disk->lastModified($path) < $cutoff && $disk->delete($path)) {
                $pending++;
            }
        }

        // 2) Uzoq vaqtdan beri arxivdagi materiallar (fayllari bilan)
        $archived = 0;

        Material::where('status', 'archived')
            ->where('updated_at', '<', now()->subDays((int) $this->option('archived-days')))
            ->with('versions')
            ->chunkById(100, function ($materials) use ($disk, &$archived) {
                foreach ($materials as $material) {
                    $paths = $material->versions->pluck('storage_path')
                        ->push($material->storage_path)
                        ->filter()
                        ->unique();

                    foreach ($paths as $path) {
                        $disk->delete($path);
                    }

                    $material->delete();
                    $archived++;
                }
            });

        // 3) Saqlash muddati tugagan versiyalar
        $versions = 0;

        MaterialVersion::where('status', 'retained')
            ->where('retain_until', '<', now())
            ->each(function (MaterialVersion $version) use ($disk, &$versions) {
                $path = $version->storage_path;

                $stillUsed = Material::where('storage_path', $path)->exists()
                    || MaterialVersion::where('storage_path', $path)->where('id', '!=', $version->id)->exists();

                if (! $stillUsed) {
                    $disk->delete($path);
                }

                $version->delete();
                $versions++;
            });

        $this->info("Tozalandi: pending fayllar={$pending}, arxiv materiallar={$archived}, versiyalar={$versions}");

        return self::SUCCESS;
    }
}
