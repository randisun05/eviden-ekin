<?php

namespace App\Services\Google;

use App\Exceptions\GoogleDriveException;
use App\Models\DriveFolder;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use Google\Client as GoogleClient;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pembungkus Google Drive API untuk SATU pengguna (Bring-Your-Own-Drive).
 *
 * Tanggung jawab:
 *  1. OAuth: memuat token terenkripsi milik user & me-refresh otomatis (dengan lock anti-race).
 *  2. Workspace: memastikan [EvidentKinerja]/[Tahun]/[Periode]/[RHK] ada (cache Redis L1 -> MySQL L2 -> Drive).
 *  3. Upload: resumable upload berbasis stream/chunk (memori konstan) + set permission "anyone with link".
 *
 * Scope yang dipakai: drive.file (hanya file/folder yang dibuat aplikasi ini) — least privilege.
 *
 * Penggunaan:
 *   $drive = GoogleDriveService::forUser($user);
 *   $result = $drive->uploadToPath(['2025', 'Triwulan I', 'RHK-01'], $localPath, 'laporan.pdf', 'application/pdf');
 */
class GoogleDriveService
{
    public const SCOPES = [Drive::DRIVE_FILE];
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    private ?GoogleClient $client = null;
    private ?Drive $drive = null;
    private ?GuzzleClient $http = null;

    public function __construct(private readonly User $user) {}

    public static function forUser(User $user): static
    {
        return app(static::class, ['user' => $user]);
    }

    /** Suntik HTTP client (untuk pengujian). */
    public function withHttpClient(GuzzleClient $http): static
    {
        $this->http = $http;

        return $this;
    }

    /** Konfigurasi dasar client OAuth; dipakai juga oleh alur login (Socialite) bila perlu. */
    public static function makeBaseClient(?GuzzleClient $http = null): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setHttpClient($http ?? new GuzzleClient([
            'connect_timeout' => 10,
            'timeout' => 120,
        ]));

        return $client;
    }

    // ------------------------------------------------------------------
    // 1. OAuth token handling
    // ------------------------------------------------------------------

    /**
     * Client yang siap pakai dengan access token valid (di-refresh bila hampir kedaluwarsa).
     */
    public function client(): GoogleClient
    {
        if ($this->client === null) {
            $this->client = self::makeBaseClient($this->http);
        }

        $this->ensureFreshToken();

        return $this->client;
    }

    private function drive(): Drive
    {
        $client = $this->client(); // selalu lewat sini agar token di-cek pada setiap operasi

        return $this->drive ??= new Drive($client);
    }

    private function ensureFreshToken(): void
    {
        $user = $this->user;

        if (! $user->google_refresh_token && ! $user->google_access_token) {
            throw GoogleDriveException::reauthRequired('Token tidak ditemukan.');
        }

        $leeway = config('evident.token_refresh_leeway', 120);
        $expiresAt = $user->token_expires_at;
        $needsRefresh = ! $user->google_access_token
            || ($expiresAt && $expiresAt->subSeconds($leeway)->isPast());

        if ($needsRefresh) {
            $this->refreshAccessToken();
        }

        $this->client->setAccessToken([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $user->token_expires_at
                ? max(1, (int) now()->diffInSeconds($user->token_expires_at, false))
                : 3600,
            'created' => time(),
        ]);
    }

    /**
     * Refresh token dengan lock per-user supaya dua worker Horizon tidak me-refresh bersamaan
     * (Google bisa merotasi token; race = token basi tersimpan).
     */
    private function refreshAccessToken(): void
    {
        $lock = Cache::lock("gdrive:token-refresh:{$this->user->id}", 30);

        $lock->block(15, function () {
            // Worker lain mungkin sudah me-refresh saat kita menunggu lock.
            $this->user->refresh();

            $expiresAt = $this->user->token_expires_at;
            $leeway = config('evident.token_refresh_leeway', 120);
            if ($this->user->google_access_token && $expiresAt && $expiresAt->subSeconds($leeway)->isFuture()) {
                return;
            }

            $refreshToken = $this->user->google_refresh_token;
            if (! $refreshToken) {
                throw GoogleDriveException::reauthRequired('Refresh token tidak tersedia.');
            }

            $oauth = self::makeBaseClient($this->http);
            try {
                $token = $oauth->fetchAccessTokenWithRefreshToken($refreshToken);
            } catch (BadResponseException $e) {
                // Endpoint token membalas 4xx/5xx sebagai exception; ambil body error OAuth-nya.
                $token = json_decode((string) $e->getResponse()->getBody(), true)
                    ?: ['error' => 'http_'.$e->getResponse()->getStatusCode()];
            }

            if (isset($token['error'])) {
                // invalid_grant = dicabut user / kedaluwarsa -> perlu login ulang, bukan retry.
                if (($token['error'] ?? '') === 'invalid_grant') {
                    throw GoogleDriveException::reauthRequired($token['error_description'] ?? '');
                }
                throw new GoogleDriveException('Gagal refresh token Google: '.($token['error_description'] ?? $token['error']));
            }

            $this->user->forceFill([
                'google_access_token' => $token['access_token'],
                // Google umumnya tidak mengirim refresh_token baru; pertahankan yang lama.
                'google_refresh_token' => $token['refresh_token'] ?? $refreshToken,
                'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            ])->save();
        });
    }

    // ------------------------------------------------------------------
    // 2. Folder workspace engine
    // ------------------------------------------------------------------

    /**
     * Pastikan struktur [EvidentKinerja]/seg1/seg2/... ada; kembalikan folder ID terdalam.
     * Setiap level di-cache: Redis (L1) -> tabel drive_folders (L2) -> Drive API.
     *
     * @param  list<string>  $segments  mis. ['2025', 'Triwulan I', 'RHK-01']
     */
    public function ensureFolderPath(array $segments): string
    {
        $parentId = $this->rootFolderId();
        $logical = [];

        foreach ($segments as $segment) {
            $logical[] = $this->sanitizeName($segment);
            $parentId = $this->resolveFolder($parentId, end($logical), implode('/', $logical));
        }

        return $parentId;
    }

    public function rootFolderId(): string
    {
        if ($this->user->gdrive_root_folder_id) {
            return $this->user->gdrive_root_folder_id;
        }

        $name = $this->sanitizeName(config('evident.drive_root_folder', 'EvidentKinerja'));
        $id = $this->resolveFolder('root', $name, '');

        $this->user->forceFill(['gdrive_root_folder_id' => $id])->save();

        return $id;
    }

    private function resolveFolder(string $parentId, string $name, string $path): string
    {
        $pathHash = hash('sha256', $path);
        $cacheKey = "gdrive:folder:{$this->user->id}:{$pathHash}";
        $ttl = config('evident.folder_cache_ttl');

        // L1: Redis
        if ($id = Cache::get($cacheKey)) {
            return $id;
        }

        // L2: MySQL (root, path '', ikut disimpan di sini juga)
        $row = DriveFolder::where('user_id', $this->user->id)->where('path_hash', $pathHash)->first();
        if ($row) {
            Cache::put($cacheKey, $row->gdrive_folder_id, $ttl);

            return $row->gdrive_folder_id;
        }

        // Miss: cari/buat di Drive, di bawah lock agar dua job paralel tidak membuat folder ganda.
        return Cache::lock("gdrive:folder-lock:{$this->user->id}:{$pathHash}", 30)->block(15, function () use ($parentId, $name, $path, $pathHash, $cacheKey, $ttl) {
            if ($id = Cache::get($cacheKey)) {
                return $id;
            }

            $id = $this->findFolder($parentId, $name) ?? $this->createFolder($parentId, $name);

            DriveFolder::updateOrCreate(
                ['user_id' => $this->user->id, 'path_hash' => $pathHash],
                ['path' => $path, 'gdrive_folder_id' => $id],
            );
            Cache::put($cacheKey, $id, $ttl);

            return $id;
        });
    }

    private function findFolder(string $parentId, string $name): ?string
    {
        $q = sprintf(
            "name = '%s' and mimeType = '%s' and '%s' in parents and trashed = false",
            $this->escapeQuery($name),
            self::FOLDER_MIME,
            $this->escapeQuery($parentId),
        );

        $res = $this->drive()->files->listFiles([
            'q' => $q,
            'fields' => 'files(id)',
            'pageSize' => 1,
            'spaces' => 'drive',
        ]);

        return $res->getFiles()[0]->id ?? null;
    }

    private function createFolder(string $parentId, string $name): string
    {
        $folder = $this->drive()->files->create(new DriveFile([
            'name' => $name,
            'mimeType' => self::FOLDER_MIME,
            'parents' => [$parentId],
        ]), ['fields' => 'id']);

        return $folder->id;
    }

    /** Hapus cache folder (L1 & L2) — dipakai saat folder ternyata sudah dihapus user di Drive. */
    public function forgetFolderCache(): void
    {
        $ids = DriveFolder::where('user_id', $this->user->id)->pluck('path_hash');
        foreach ($ids as $hash) {
            Cache::forget("gdrive:folder:{$this->user->id}:{$hash}");
        }
        DriveFolder::where('user_id', $this->user->id)->delete();
        $this->user->forceFill(['gdrive_root_folder_id' => null])->save();
    }

    // ------------------------------------------------------------------
    // 3. Upload (resumable, chunked stream) + permission
    // ------------------------------------------------------------------

    /**
     * Pastikan folder ada lalu upload. Jika folder cache ternyata basi (dihapus user di Drive),
     * cache dibersihkan dan upload diulang satu kali.
     *
     * @param  list<string>  $folderSegments
     * @return array{id:string,name:string,mime_type:?string,size:?int,web_view_link:string,folder_id:string}
     */
    public function uploadToPath(array $folderSegments, string $localPath, string $fileName, ?string $mimeType = null, array $appProperties = []): array
    {
        return $this->withAuthRetry(function () use ($folderSegments, $localPath, $fileName, $mimeType, $appProperties) {
            $folderId = $this->ensureFolderPath($folderSegments);

            try {
                return $this->uploadFile($localPath, $fileName, $folderId, $mimeType, $appProperties);
            } catch (GoogleServiceException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
                Log::warning('Folder Drive tidak ditemukan, cache dibersihkan & upload diulang.', ['user' => $this->user->id]);
                $this->forgetFolderCache();

                return $this->uploadFile($localPath, $fileName, $this->ensureFolderPath($folderSegments), $mimeType, $appProperties);
            }
        });
    }

    /**
     * Google dapat menolak access token (401) sebelum waktu kedaluwarsa lokal (dicabut, jam bergeser).
     * Paksa refresh SATU kali lalu ulangi; bila tetap 401, user harus login ulang.
     */
    public function withAuthRetry(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (GoogleServiceException $e) {
            if ($e->getCode() !== 401) {
                throw $e;
            }
        }

        $this->user->forceFill(['google_access_token' => null, 'token_expires_at' => null])->save();
        $this->client = $this->drive = null;

        try {
            return $operation();
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 401) {
                throw GoogleDriveException::reauthRequired('Google menolak kredensial setelah refresh.');
            }
            throw $e;
        }
    }

    /**
     * Upload file lokal ke folder Drive via resumable upload, dibaca per-chunk dari handle file
     * (tidak memuat seluruh file ke memori), lalu set permission "anyone with the link: reader".
     *
     * @return array{id:string,name:string,mime_type:?string,size:?int,web_view_link:string,folder_id:string}
     */
    public function uploadFile(string $localPath, string $fileName, string $folderId, ?string $mimeType = null, array $appProperties = []): array
    {
        if (! is_file($localPath) || ! is_readable($localPath)) {
            throw new GoogleDriveException("File sumber tidak ditemukan: {$localPath}");
        }

        $mimeType ??= mime_content_type($localPath) ?: 'application/octet-stream';
        $size = filesize($localPath);
        $chunkSize = (int) config('evident.upload_chunk_size');

        $client = $this->client();
        $drive = $this->drive();

        $meta = new DriveFile([
            'name' => $this->sanitizeName($fileName),
            'parents' => [$folderId],
            // Penanda untuk idempotensi: job yang di-retry bisa menemukan file yang sudah terunggah.
            'appProperties' => $appProperties ?: null,
        ]);

        $handle = fopen($localPath, 'rb');
        $client->setDefer(true); // buat request tanpa langsung dieksekusi
        try {
            $request = $drive->files->create($meta, ['fields' => 'id,name,mimeType,size,webViewLink']);

            $media = new MediaFileUpload($client, $request, $mimeType, null, true, $chunkSize);
            $media->setFileSize($size);

            $status = false;
            while (! $status && ! feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                $status = $media->nextChunk($chunk);
            }
        } finally {
            $client->setDefer(false);
            fclose($handle);
        }

        if (! $status instanceof DriveFile) {
            throw new GoogleDriveException('Upload ke Google Drive tidak selesai.');
        }

        $this->makePublicReader($status->id);

        return [
            'id' => $status->id,
            'name' => $status->name,
            'mime_type' => $status->mimeType,
            'size' => $status->size !== null ? (int) $status->size : $size,
            'web_view_link' => $status->webViewLink ?: "https://drive.google.com/file/d/{$status->id}/view",
            'folder_id' => $folderId,
        ];
    }

    /**
     * Cari file yang sebelumnya diunggah dengan appProperties tertentu (dipakai saat retry job).
     *
     * @return array{id:string,name:string,mime_type:?string,size:?int,web_view_link:string,folder_id:string}|null
     */
    public function findByAppProperty(string $folderId, string $key, string $value): ?array
    {
        $q = sprintf(
            "'%s' in parents and appProperties has { key='%s' and value='%s' } and trashed = false",
            $this->escapeQuery($folderId), $this->escapeQuery($key), $this->escapeQuery($value),
        );
        $res = $this->drive()->files->listFiles([
            'q' => $q, 'fields' => 'files(id,name,mimeType,size,webViewLink)', 'pageSize' => 1,
        ]);
        $f = $res->getFiles()[0] ?? null;
        if (! $f) {
            return null;
        }

        // Pastikan permission publik ada (upload sebelumnya mungkin gagal tepat sebelum langkah ini).
        $this->makePublicReader($f->id);

        return [
            'id' => $f->id, 'name' => $f->name, 'mime_type' => $f->mimeType,
            'size' => $f->size !== null ? (int) $f->size : null,
            'web_view_link' => $f->webViewLink ?: "https://drive.google.com/file/d/{$f->id}/view",
            'folder_id' => $folderId,
        ];
    }

    /** "Anyone with the link can view". */
    public function makePublicReader(string $fileId): void
    {
        $this->drive()->permissions->create($fileId, new Permission([
            'type' => 'anyone',
            'role' => 'reader',
            'allowFileDiscovery' => false,
        ]), ['fields' => 'id']);
    }

    public function deleteFile(string $fileId): void
    {
        try {
            $this->drive()->files->delete($fileId);
        } catch (GoogleServiceException $e) {
            if ($e->getCode() !== 404) { // sudah tidak ada = tujuan tercapai
                throw $e;
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function escapeQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    /** Nama folder/file aman untuk Drive & path logis (tanpa "/" dan karakter kontrol). */
    private function sanitizeName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '-', $name) ?? '');

        return $name === '' ? 'tanpa-nama' : mb_substr($name, 0, 200);
    }
}
