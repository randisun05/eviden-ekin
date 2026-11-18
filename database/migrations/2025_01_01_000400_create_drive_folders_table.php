<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache persisten (L2) path folder -> Drive folder ID.
     * L1 = Redis. Mencegah API search berulang.
     */
    public function up(): void
    {
        Schema::create('drive_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path_hash', 64); // sha256 dari path logis, mis. "2025/Triwulan I/RHK-01"
            $table->string('path');
            $table->string('gdrive_folder_id');
            $table->timestamps();

            $table->unique(['user_id', 'path_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_folders');
    }
};
