<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Satu bukti (evident) dapat berisi banyak berkas. Data berkas dipindah dari kolom
 * evidents.* ke tabel evident_files; evidents.status menjadi status gabungan.
 */
return new class extends Migration
{
    private const OLD_COLUMNS = ['gdrive_file_id', 'gdrive_web_view_link', 'gdrive_folder_id', 'file_name', 'file_size', 'mime_type'];

    public function up(): void
    {
        Schema::create('evident_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evident_id')->constrained('evidents')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('gdrive_file_id')->nullable();
            $table->text('gdrive_web_view_link')->nullable();
            $table->string('gdrive_folder_id')->nullable();
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->text('error')->nullable();
            $table->boolean('reauth_required')->default(false);
            $table->timestamps();

            $table->index(['evident_id', 'position']);
        });

        // Pindahkan berkas bukti yang sudah ada (satu berkas per bukti).
        DB::table('evidents')->where('evident_type', 'file_upload')->orderBy('created_at')->each(function ($e) {
            DB::table('evident_files')->insert([
                'id' => (string) Str::uuid(),
                'evident_id' => $e->id,
                'position' => 0,
                'file_name' => $e->file_name ?? 'berkas',
                'file_size' => $e->file_size,
                'mime_type' => $e->mime_type,
                'gdrive_file_id' => $e->gdrive_file_id,
                'gdrive_web_view_link' => $e->gdrive_web_view_link,
                'gdrive_folder_id' => $e->gdrive_folder_id,
                'status' => $e->status,
                'created_at' => $e->created_at,
                'updated_at' => $e->updated_at,
            ]);
        });

        Schema::table('evidents', fn (Blueprint $table) => $table->dropColumn(self::OLD_COLUMNS));
    }

    public function down(): void
    {
        Schema::table('evidents', function (Blueprint $table) {
            $table->string('gdrive_file_id')->nullable();
            $table->text('gdrive_web_view_link')->nullable();
            $table->string('gdrive_folder_id')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
        });

        // Hanya berkas pertama per bukti yang dapat dikembalikan ke skema lama.
        DB::table('evident_files')->orderBy('position')->get()->unique('evident_id')->each(
            fn ($f) => DB::table('evidents')->where('id', $f->evident_id)->update([
                'gdrive_file_id' => $f->gdrive_file_id, 'gdrive_web_view_link' => $f->gdrive_web_view_link,
                'gdrive_folder_id' => $f->gdrive_folder_id, 'file_name' => $f->file_name,
                'file_size' => $f->file_size, 'mime_type' => $f->mime_type,
            ])
        );

        Schema::dropIfExists('evident_files');
    }
};
