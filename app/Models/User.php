<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'google_access_token',
        'google_refresh_token',
        'token_expires_at',
        'gdrive_root_folder_id',
    ];

    protected $hidden = [
        'google_access_token',
        'google_refresh_token',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            // Dienkripsi dengan APP_KEY (AES-256-CBC/GCM) saat disimpan.
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }
}
