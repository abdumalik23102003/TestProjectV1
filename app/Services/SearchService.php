<?php

namespace App\Services;

use App\Models\Material;
use App\Models\User;

class SearchService
{
    public function search(User $user, string $query, int $page = 1)
    {
        $baseQuery = Material::where('status', 'active');

        // Public materials
        $public = $baseQuery->clone()
            ->where('visibility', 'public')
            ->whereRaw("MATCH(title, description) AGAINST(? IN BOOLEAN MODE)", [$query])
            ->get();

        // Private materials — faqat bog'langan ustozlarning
        $private = $baseQuery->clone()
            ->where('visibility', 'private')
            ->whereHas('teacher', function ($q) use ($user) {
                $q->whereHas('studentConnections', function ($sq) use ($user) {
                    $sq->where('student_id', $user->id)
                        ->where('status', 'active');
                });
            })
            ->whereRaw("MATCH(title, description) AGAINST(? IN BOOLEAN MODE)", [$query])
            ->get();

        return $public->merge($private)->paginate(5, ['*'], 'page', $page);
    }

    public function searchByCategory(User $user, int $categoryId, int $page = 1)
    {
        $baseQuery = Material::where('status', 'active')
            ->where('category_id', $categoryId);

        // Public materials
        $public = $baseQuery->clone()
            ->where('visibility', 'public')
            ->get();

        // Private materials
        $private = $baseQuery->clone()
            ->where('visibility', 'private')
            ->whereHas('teacher', function ($q) use ($user) {
                $q->whereHas('studentConnections', function ($sq) use ($user) {
                    $sq->where('student_id', $user->id)
                        ->where('status', 'active');
                });
            })
            ->get();

        return $public->merge($private)->paginate(5, ['*'], 'page', $page);
    }
}
