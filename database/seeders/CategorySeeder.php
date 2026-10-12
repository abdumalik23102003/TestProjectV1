<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Matematika', 'slug' => 'matematika', 'sort_order' => 1],
            ['name' => 'Fizika', 'slug' => 'fizika', 'sort_order' => 2],
            ['name' => 'Kimyo', 'slug' => 'kimyo', 'sort_order' => 3],
            ['name' => 'Biologiya', 'slug' => 'biologiya', 'sort_order' => 4],
            ['name' => 'Tarix', 'slug' => 'tarix', 'sort_order' => 5],
            ['name' => 'Adabiyot', 'slug' => 'adabiyot', 'sort_order' => 6],
            ['name' => 'Ingliz tili', 'slug' => 'ingliz-tili', 'sort_order' => 7],
            ['name' => 'Informatika', 'slug' => 'informatika', 'sort_order' => 8],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
