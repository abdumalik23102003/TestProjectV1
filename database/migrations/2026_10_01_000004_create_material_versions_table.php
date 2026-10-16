<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->unsignedInteger('version_number');
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum');
            $table->timestamp('uploaded_at');
            $table->enum('status', ['active', 'replaced', 'retained'])->default('active');
            $table->timestamp('retain_until')->nullable();
            $table->timestamps();

            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            $table->unique(['material_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_versions');
    }
};
