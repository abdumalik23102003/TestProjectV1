<?php

namespace App\Services;

use App\Models\Material;
use App\Models\User;

class SearchService
{
    public function search(User $user, string $query): \Illuminate\Pagination\Paginator
    {
        $baseQuery = Material::where('status', 'active');

        // Public materials
        $public = $baseQuery->clone()
            ->where('visibility', 'public')
            ->where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get();

        // Private materials (only from active connections)
        $private = $baseQuery->clone()
            ->where('visibility', 'private')
            ->whereHas('teacher', function ($q) use ($user) {
                $q->whereHas('studentConnections', function ($sq) use ($user) {
                    $sq->where('student_id', $user->id)->where('status', 'active');
                });
            })
            ->where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get();

        $results = $public->merge($private);

        return new \Illuminate\Pagination\Paginator(
            $results,
            5,
            1
        );
    }
}
