<?php

namespace App\Services;

use App\Models\Material;
use App\Models\User;
use App\Support\LikeEscape;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Material qidiruvi (sarlavha, tavsif, fayl nomi bo'yicha).
 *
 * Eslatma: InnoDB FULLTEXT ishlatilmaydi: u tranzaksiya yopilmaguncha yangi qatorlarni
 * ko'rmaydi va qisqa so'zlarni topmaydi. Escape qilingan LIKE oddiy va ishonchli.
 */
class SearchService
{
    /**
     * O'quvchi uchun: ochiq materiallar + o'zi bilan faol bog'langan ustozlarning shaxsiy materiallari.
     * Faqat faol ustozlarning materiallari ko'rinadi.
     */
    public function search(User $user, string $query, int $page = 1): LengthAwarePaginator
    {
        $materials = Material::query()
            ->where('materials.status', 'active')
            ->whereHas('teacher', fn ($teacher) => $this->activeTeacher($teacher))
            ->where(function ($visible) use ($user) {
                $visible->where('visibility', 'public')
                    ->orWhere(function ($private) use ($user) {
                        $private->where('visibility', 'private')
                            ->whereHas('teacher', function ($teacher) use ($user) {
                                $teacher->whereHas('studentConnections', function ($connection) use ($user) {
                                    $connection
                                        ->where('teacher_students.student_id', $user->id)
                                        ->where('teacher_students.status', 'active');
                                });
                            });
                    });
            });

        $this->applyTerm($materials, $query);

        return $materials
            ->with('teacher')
            ->orderByDesc('id')
            ->paginate(5, ['*'], 'page', max(1, $page));
    }

    /**
     * Ustoz uchun: 'my' (o'zining barcha materiallari) yoki 'shared' (faol ustozlarning ochiq materiallari).
     */
    public function searchForTeacher(User $teacher, string $query, string $scope, int $limit = 10): Collection
    {
        $materials = Material::query()
            ->with('teacher')
            ->where('status', 'active');

        $this->applyTerm($materials, $query);

        if ($scope === 'my') {
            $materials->where('teacher_id', $teacher->id);
        } else {
            $materials->where('visibility', 'public')
                ->whereHas('teacher', fn ($t) => $this->activeTeacher($t));
        }

        return $materials->latest()->limit($limit)->get();
    }

    private function activeTeacher($teacher): void
    {
        $teacher->where('role', 'teacher')->where('status', 'active');
    }

    private function applyTerm(Builder $materials, string $query): void
    {
        $like = LikeEscape::contains(trim($query));

        $materials->where(function ($match) use ($like) {
            $match->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('original_filename', 'like', $like);
        });
    }
}
