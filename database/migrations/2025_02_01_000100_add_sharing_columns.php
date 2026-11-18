<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Halaman bundling per-RHK (/v/{kode 10 huruf}) + masa berlaku link.
     * Kode RHK (10) dan evident (8) berbeda panjang sehingga namespace-nya tidak bisa bertabrakan.
     */
    public function up(): void
    {
        Schema::table('rhks', function (Blueprint $table) {
            $table->string('app_short_code', 12)->nullable()->unique()->after('code');
            $table->boolean('is_protected')->default(false);
            $table->string('access_pin')->nullable(); // hash
            $table->timestamp('expires_at')->nullable();
        });

        Schema::table('evidents', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('access_pin');
        });

        // Backfill RHK yang sudah ada.
        DB::table('rhks')->whereNull('app_short_code')->orderBy('created_at')->each(function ($r) {
            do {
                $code = Str::lower(Str::random(10));
            } while (DB::table('rhks')->where('app_short_code', $code)->exists());
            DB::table('rhks')->where('id', $r->id)->update(['app_short_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('evidents', fn (Blueprint $t) => $t->dropColumn('expires_at'));
        Schema::table('rhks', function (Blueprint $t) {
            $t->dropUnique(['app_short_code']);
            $t->dropColumn(['app_short_code', 'is_protected', 'access_pin', 'expires_at']);
        });
    }
};
