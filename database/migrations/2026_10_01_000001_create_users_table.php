<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->string('full_name');
            $table->enum('role', ['teacher', 'student', 'admin'])->default('student');
            $table->enum('status', ['active', 'pending', 'rejected', 'blocked'])->default('pending');
            $table->string('teacher_approval_token')->nullable()->unique();
            $table->timestamp('teacher_approved_at')->nullable();
            $table->timestamps();
            
            $table->index('telegram_user_id');
            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
