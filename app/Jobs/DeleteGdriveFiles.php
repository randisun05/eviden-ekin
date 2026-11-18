<?php

namespace App\Jobs;

use App\Exceptions\GoogleDriveException;
use App\Models\User;
use App\Services\Google\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hapus file di Drive pengguna setelah record evident dihapus (di luar request HTTP). */
class DeleteGdriveFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @param list<string> $fileIds */
    public function __construct(public int $userId, public array $fileIds)
    {
        $this->onQueue('default');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user || $this->fileIds === []) {
            return;
        }

        $drive = GoogleDriveService::forUser($user);
        try {
            foreach ($this->fileIds as $id) {
                $drive->deleteFile($id); // 404 dianggap sukses
            }
        } catch (GoogleDriveException $e) {
            if ($e->getCode() === 401) { // token dicabut: tidak bisa membersihkan, jangan retry
                $this->fail($e);

                return;
            }
            throw $e;
        }
    }
}
