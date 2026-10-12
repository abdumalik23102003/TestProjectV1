<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id');
            $table->enum('process_type', ['upload', 'edit', 'search', 'approval'])->default('upload');
            $table->string('current_step');
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->unique(['telegram_user_id', 'process_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_sessions');
    }
};
