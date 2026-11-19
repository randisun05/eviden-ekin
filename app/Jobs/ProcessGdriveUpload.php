<?php

namespace App\Jobs;

use App\Exceptions\GoogleDriveException;
use App\Models\EvidentFile;
use App\Services\Google\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Unggah SATU berkas staging ke Google Drive milik user, perbarui record berkas -> 'ready',
 * hitung ulang status bukti induknya, lalu hapus file staging.
 */
class ProcessGdriveUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 900; // harus < queue.connections.redis.retry_after

    public function __construct(public string $fileId, public string $stagingPath)
    {
        $this->onQueue('uploads');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $file = EvidentFile::with('evident.rhk.period.user')->find($this->fileId);

        // Berkas/bukti dihapus user saat antre, atau sudah selesai (retry/duplikasi): bersihkan & selesai.
        if (! $file || $file->status === 'ready') {
            $this->cleanup();

            return;
        }

        $evident = $file->evident;
        $period = $evident->rhk->period;
        $segments = [(string) $period->year, $period->title, $evident->rhk->code];
        $marker = ['evident_file_id' => $file->id];
        $drive = GoogleDriveService::forUser($period->user);

        try {
            $result = null;

            // Retry: mungkin upload sebelumnya sudah sukses sebelum DB sempat diperbarui.
            if ($this->attempts() > 1) {
                $folderId = $drive->ensureFolderPath($segments);
                $result = $drive->findByAppProperty($folderId, 'evident_file_id', $file->id);
            }

            $result ??= $drive->uploadToPath(
                $segments,
                Storage::disk('staging')->path($this->stagingPath),
                $file->file_name ?? basename($this->stagingPath),
                $file->mime_type,
                $marker,
            );
        } catch (GoogleDriveException $e) {
            if ($e->getCode() === 401) { // butuh login ulang: retry tidak ada gunanya
                $this->fail($e);

                return;
            }
            throw $e;
        }

        // Berkas/bukti dihapus user selagi upload berjalan: jangan biarkan file yatim di Drive.
        if (! EvidentFile::whereKey($file->id)->exists()) {
            $drive->deleteFile($result['id']);
            $this->cleanup();

            return;
        }

        $file->update([
            'gdrive_file_id' => $result['id'],
            'gdrive_web_view_link' => $result['web_view_link'],
            'gdrive_folder_id' => $result['folder_id'],
            'file_size' => $result['size'] ?? $file->file_size,
            'status' => 'ready',
            'error' => null,
            'reauth_required' => false,
        ]);
        $evident->refreshStatus();

        $this->cleanup();
    }

    /** Dipanggil setelah semua percobaan habis / job di-fail. */
    public function failed(Throwable $e): void
    {
        $file = EvidentFile::find($this->fileId);
        if ($file) {
            $file->update([
                'status' => 'failed',
                'error' => mb_substr($this->readableError($e), 0, 500),
                'reauth_required' => $e instanceof GoogleDriveException && $e->getCode() === 401,
            ]);
            $file->evident?->refreshStatus();
        }

        $this->cleanup();
    }

    /** Pesan error Google berupa JSON mentah; ambil "message"-nya saja. */
    private function readableError(Throwable $e): string
    {
        $json = json_decode($e->getMessage(), true);

        return is_array($json) ? ($json['error']['message'] ?? $e->getMessage()) : $e->getMessage();
    }

    private function cleanup(): void
    {
        Storage::disk('staging')->delete($this->stagingPath);
    }
}
