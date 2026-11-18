<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rhk_id')->constrained('rhks')->cascadeOnDelete();
            $table->string('title');
            $table->enum('evident_type', ['file_upload', 'external_link']);
            $table->text('external_url')->nullable();
            $table->string('gdrive_file_id')->nullable();
            $table->text('gdrive_web_view_link')->nullable();
            $table->string('gdrive_folder_id')->nullable();
            $table->string('app_short_code', 12)->unique();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->boolean('is_protected')->default(false);
            $table->string('access_pin')->nullable(); // simpan HASH (Hash::make), bukan plaintext
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['rhk_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidents');
    }
};
