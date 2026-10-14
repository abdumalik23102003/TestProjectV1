<?php

namespace App\Services;

use App\Models\Material;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SearchService
{
    public function search(
        User $user,
        string $query,
        int $page = 1
    ): LengthAwarePaginator {
        $query = trim($query);

        return Material::query()
            ->where('materials.status', 'active')
            ->where(function ($materials) use ($user, $query) {
                // Public materials
                $materials->where(function ($public) use ($query) {
                    $public->where('visibility', 'public')
                        ->where(function ($match) use ($query) {
                            $match->where('title', 'like', "%{$query}%")
                                ->orWhere(
                                    'description',
                                    'like',
                                    "%{$query}%"
                                );
                        });
                })

                    // Private materials: only from connected teachers
                    ->orWhere(function ($private) use ($user, $query) {
                        $private->where('visibility', 'private')
                            ->whereHas('teacher', function ($teacher) use ($user) {
                                $teacher->whereHas(
                                    'studentConnections',
                                    function ($connection) use ($user) {
                                        $connection
                                            ->where('teacher_students.student_id', $user->id)
                                            ->where('teacher_students.status', 'active');
                                    }
                                );
                            })
                            ->where(function ($match) use ($query) {
                                $match->where('title', 'like', "%{$query}%")
                                    ->orWhere(
                                        'description',
                                        'like',
                                        "%{$query}%"
                                    );
                            });
                    });
            })
            ->with('teacher')
            ->orderByDesc('id')
            ->paginate(5, ['*'], 'page', max(1, $page));
    }
}
