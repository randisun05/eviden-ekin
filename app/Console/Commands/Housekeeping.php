<?php

namespace App\Console\Commands;

use App\Models\Evident;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class Housekeeping extends Command
{
    protected $signature = 'evident:housekeeping {--dry-run : Hanya tampilkan, jangan ubah apa pun}';

    protected $description = 'Hapus berkas staging yatim dan tandai upload yang macet sebagai gagal';

    public function handle(): int
    {
        $cutoff = now()->subHours(config('evident.staging_ttl_hours'));
        $dry = $this->option('dry-run');

        // 1) Berkas staging yatim (mis. worker mati sebelum hook failed() sempat berjalan).
        $disk = Storage::disk('staging');
        $removed = 0;
        foreach ($disk->files() as $file) {
            if ($file === '.gitignore') {
                continue;
            }
            if ($disk->lastModified($file) < $cutoff->getTimestamp()) {
                $dry || $disk->delete($file);
                $removed++;
            }
        }

        // 2) Upload 'processing' yang tidak kunjung selesai: job hilang/macet -> gagal agar user bisa unggah ulang.
        $stuck = Evident::where('status', 'processing')->where('created_at', '<', $cutoff);
        $failed = $stuck->count();
        if (! $dry) {
            $stuck->get()->each(function (Evident $e) {
                $e->files()->where('status', 'processing')->update(['status' => 'failed', 'error' => 'Unggahan tidak selesai (kedaluwarsa).']);
                $e->update([
                    'status' => 'failed',
                    'metadata' => array_merge($e->metadata ?? [], ['error' => 'Unggahan tidak selesai (kedaluwarsa).']),
                ]);
            });
        }

        $this->info(($dry ? '[dry-run] ' : '')."Staging dihapus: {$removed}; upload macet ditandai gagal: {$failed}.");

        return self::SUCCESS;
    }
}
