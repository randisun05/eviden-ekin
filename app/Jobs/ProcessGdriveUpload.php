<?php

namespace App\Jobs;

use App\Exceptions\GoogleDriveException;
use App\Models\Evident;
use App\Services\Google\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Unggah file staging ke Google Drive milik user, set permission publik,
 * perbarui record evident -> 'ready', lalu hapus file staging.
 */
class ProcessGdriveUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 900; // harus < queue.connections.redis.retry_after

    public function __construct(public string $evidentId, public string $stagingPath)
    {
        $this->onQueue('uploads');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $evident = Evident::with('rhk.period.user')->find($this->evidentId);

        // Record dihapus user saat antre, atau sudah selesai (retry/duplikasi): bersihkan & selesai.
        if (! $evident || $evident->status === 'ready') {
            $this->cleanup();

            return;
        }

        $period = $evident->rhk->period;
        $segments = [(string) $period->year, $period->title, $evident->rhk->code];
        $marker = ['evident_id' => $evident->id];
        $drive = GoogleDriveService::forUser($period->user);

        try {
            $result = null;

            // Retry: mungkin upload sebelumnya sudah sukses sebelum DB sempat diperbarui.
            if ($this->attempts() > 1) {
                $folderId = $drive->ensureFolderPath($segments);
                $result = $drive->findByAppProperty($folderId, 'evident_id', $evident->id);
            }

            $result ??= $drive->uploadToPath(
                $segments,
                Storage::disk('staging')->path($this->stagingPath),
                $evident->file_name ?? basename($this->stagingPath),
                $evident->mime_type,
                $marker,
            );
        } catch (GoogleDriveException $e) {
            if ($e->getCode() === 401) { // butuh login ulang: retry tidak ada gunanya
                $this->fail($e);

                return;
            }
            throw $e;
        }

        $evident->update([
            'gdrive_file_id' => $result['id'],
            'gdrive_web_view_link' => $result['web_view_link'],
            'gdrive_folder_id' => $result['folder_id'],
            'file_size' => $result['size'] ?? $evident->file_size,
            'status' => 'ready',
            'metadata' => array_merge($evident->metadata ?? [], ['error' => null]),
        ]);

        $this->cleanup();
    }

    /** Dipanggil setelah semua percobaan habis / job di-fail. */
    public function failed(Throwable $e): void
    {
        $evident = Evident::find($this->evidentId);
        $evident?->update([
            'status' => 'failed',
            'metadata' => array_merge($evident->metadata ?? [], [
                'error' => mb_substr($e->getMessage(), 0, 500),
                'reauth_required' => $e instanceof GoogleDriveException && $e->getCode() === 401,
            ]),
        ]);

        $this->cleanup();
    }

    private function cleanup(): void
    {
        Storage::disk('staging')->delete($this->stagingPath);
    }
}
