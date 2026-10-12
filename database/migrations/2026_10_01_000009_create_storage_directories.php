<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Private materials disk uchun storage directory
        if (!file_exists(storage_path('private_materials'))) {
            mkdir(storage_path('private_materials'), 0755, true);
        }
    }

    public function down(): void
    {
        // Optional: cleanup
    }
};
