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

    // Batas ukuran upload (MB). Samakan dengan upload_max_filesize/post_max_size PHP & client_max_body_size Nginx.
    'max_upload_mb' => (int) env('EVIDENT_MAX_UPLOAD_MB', 50),

    // Ekstensi yang diizinkan (divalidasi dari isi file, bukan hanya nama).
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
];
