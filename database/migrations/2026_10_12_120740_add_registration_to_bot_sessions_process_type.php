<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE bot_sessions
            MODIFY process_type
            ENUM('upload', 'edit', 'search', 'approval', 'registration')
            NOT NULL DEFAULT 'upload'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE bot_sessions
            MODIFY process_type
            ENUM('upload', 'edit', 'search', 'approval')
            NOT NULL DEFAULT 'upload'
        ");
    }
};
