<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Uraian pekerjaan yang dibuktikan oleh bukti ini. */
    public function up(): void
    {
        Schema::table('evidents', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('evidents', fn (Blueprint $t) => $t->dropColumn('description'));
    }
};
