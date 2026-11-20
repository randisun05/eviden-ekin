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
     * Simpan semua berkas ke staging lokal, buat satu bukti 'processing' berisi N berkas,
     * lalu lempar satu job per berkas ke antrean. Tidak ada panggilan ke Google di request HTTP.
     *
     * @param  list<UploadedFile>  $files
     */
    public function storeFiles(Rhk $rhk, array $files, string $title, string $description, ?string $pin = null): Evident
    {
        $staged = []; // [UploadedFile, stagingName]
        try {
            foreach ($files as $file) {
                $name = Str::uuid()->toString().'.'.($file->guessExtension() ?: 'bin');
                $file->storeAs('', $name, 'staging');
                $staged[] = [$file, $name];
            }

            [$evident, $rows] = DB::transaction(function () use ($rhk, $staged, $title, $description, $pin) {
                $evident = Evident::create([
                    'rhk_id' => $rhk->id,
                    'title' => $title,
                    'description' => $description,
                    'evident_type' => 'file_upload',
                    'is_protected' => $pin !== null,
                    'access_pin' => $pin,
                    'status' => 'processing',
                ]);

                $rows = [];
                foreach ($staged as $i => [$file, $name]) {
                    $rows[] = [$evident->files()->create([
                        'position' => $i,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'status' => 'processing',
                    ]), $name];
                }

                return [$evident, $rows];
            });
        } catch (\Throwable $e) {
            foreach ($staged as [, $name]) {
                Storage::disk('staging')->delete($name);
            }
            throw $e;
        }

        foreach ($rows as [$row, $name]) {
            ProcessGdriveUpload::dispatch($row->id, $name); // after_commit=true di queue.php
        }

        return $evident;
    }

    public function storeExternalLink(Rhk $rhk, string $title, string $description, string $url, ?string $pin = null): Evident
    {
        return Evident::create([
            'rhk_id' => $rhk->id,
            'title' => $title,
            'description' => $description,
            'evident_type' => 'external_link',
            'external_url' => $url,
            'is_protected' => $pin !== null,
            'access_pin' => $pin,
            'status' => 'ready',
        ]);
    }
}
