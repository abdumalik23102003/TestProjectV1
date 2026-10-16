<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->string('original_filename');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->string('storage_disk')->default('private_materials');
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type');
            $table->string('checksum');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index('teacher_id');
            $table->index('visibility');
            $table->index('status');
            $table->fullText(['title', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
