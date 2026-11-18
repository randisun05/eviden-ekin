<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rhks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('period_id')->constrained('periods')->cascadeOnDelete();
            $table->string('code');
            $table->text('title');
            $table->text('target_description')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rhks');
    }
};
