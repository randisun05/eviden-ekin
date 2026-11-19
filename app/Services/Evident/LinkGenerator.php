<?php

namespace App\Services\Evident;

use App\Models\Evident;
use App\Models\Rhk;

/** Dual-link: tautan langsung (Drive / eksternal) dan shortlink landing page aplikasi. */
class LinkGenerator
{
    public function shortUrl(string $code): string
    {
        return config('evident.shortlink_base').'/v/'.$code;
    }

    /** @return array{direct:?string,short:string} */
    public function forEvident(Evident $e): array
    {
        return [
            // Berkas tunggal: tautan Drive-nya. Banyak berkas: tidak ada satu tautan langsung (pakai shortlink).
            'direct' => $e->evident_type === 'file_upload'
                ? ($e->files->count() === 1 ? $e->files->first()->gdrive_web_view_link : null)
                : $e->external_url,
            'short' => $this->shortUrl($e->app_short_code),
        ];
    }

    public function forRhk(Rhk $r): string
    {
        return $this->shortUrl($r->app_short_code);
    }
}
