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

    // Satu bukti boleh berisi beberapa berkas. Batas jumlah & total ukuran (MB) per bukti.
    // Samakan: post_max_size PHP & client_max_body_size Nginx >= max_total_mb + sedikit ruang,
    // dan max_file_uploads PHP >= max_files.
    'max_files' => (int) env('EVIDENT_MAX_FILES', 20),
    'max_total_mb' => (int) env('EVIDENT_MAX_TOTAL_MB', 90),

    // Ekstensi yang diizinkan (divalidasi dari isi file, bukan hanya nama).
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],

    // Basis URL shortlink (mis. https://evident.domain.com). Default: APP_URL.
    'shortlink_base' => rtrim(env('EVIDENT_SHORTLINK_BASE', env('APP_URL', 'http://localhost')), '/'),

    // Pembatasan tebak-PIN halaman publik.
    'pin_max_attempts' => 5,          // per IP per target
    'pin_decay_seconds' => 600,
    'pin_target_max_attempts' => 30,  // semua IP per target, per jam

    // Berkas staging & upload 'processing' yang lebih tua dari ini dianggap yatim/macet (jam).
    // Retry job hanya ~8 menit; 48 jam sudah jauh melewati antrean normal.
    'staging_ttl_hours' => (int) env('EVIDENT_STAGING_TTL_HOURS', 48),
];
