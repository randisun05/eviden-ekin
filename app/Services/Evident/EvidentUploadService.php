<?php

namespace App\Services\Evident;

use App\Jobs\ProcessGdriveUpload;
use App\Models\Evident;
use App\Models\Rhk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EvidentUploadService
{
    /**
     * Simpan file ke staging lokal, buat record 'processing', lempar job ke antrean.
     * Tidak ada panggilan ke Google di dalam request HTTP.
     */
    public function storeFile(Rhk $rhk, UploadedFile $file, string $title, ?string $pin = null): Evident
    {
        $original = $file->getClientOriginalName();
        $stagingName = Str::uuid()->toString().'.'.($file->guessExtension() ?: 'bin');
        $file->storeAs('', $stagingName, 'staging');

        try {
            $evident = DB::transaction(fn () => Evident::create([
                'rhk_id' => $rhk->id,
                'title' => $title,
                'evident_type' => 'file_upload',
                'file_name' => $original,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_protected' => $pin !== null,
                'access_pin' => $pin,
                'status' => 'processing',
            ]));
        } catch (\Throwable $e) {
            Storage::disk('staging')->delete($stagingName);
            throw $e;
        }

        ProcessGdriveUpload::dispatch($evident->id, $stagingName); // after_commit=true di queue.php

        return $evident;
    }

    public function storeExternalLink(Rhk $rhk, string $title, string $url, ?string $pin = null): Evident
    {
        return Evident::create([
            'rhk_id' => $rhk->id,
            'title' => $title,
            'evident_type' => 'external_link',
            'external_url' => $url,
            'is_protected' => $pin !== null,
            'access_pin' => $pin,
            'status' => 'ready',
        ]);
    }
}
