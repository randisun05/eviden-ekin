<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->year('year');
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
