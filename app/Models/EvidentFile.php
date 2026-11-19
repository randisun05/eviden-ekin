<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu berkas di dalam sebuah bukti (Evident). */
class EvidentFile extends Model
{
    use HasUuidV4;

    protected $fillable = [
        'evident_id', 'position', 'file_name', 'file_size', 'mime_type',
        'gdrive_file_id', 'gdrive_web_view_link', 'gdrive_folder_id',
        'status', 'error', 'reauth_required',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'position' => 'integer', 'reauth_required' => 'boolean'];
    }

    public function evident(): BelongsTo
    {
        return $this->belongsTo(Evident::class);
    }
}
