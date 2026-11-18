<?php

return [
    // Nama folder akar di Drive pengguna.
    'drive_root_folder' => env('EVIDENT_DRIVE_ROOT', 'EvidentKinerja'),

    // TTL cache Redis (L1) untuk folder ID, detik. MySQL (L2) bersifat permanen.
    'folder_cache_ttl' => (int) env('EVIDENT_FOLDER_CACHE_TTL', 60 * 60 * 24 * 7),

    // Ukuran chunk resumable upload (harus kelipatan 256 KiB). Default 8 MiB.
    'upload_chunk_size' => (int) env('EVIDENT_UPLOAD_CHUNK', 8 * 1024 * 1024),

    // Refresh token sedikit lebih awal dari kedaluwarsa, detik.
    'token_refresh_leeway' => 120,
];
