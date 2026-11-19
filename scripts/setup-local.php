<?php

/**
 * Penyiapan lingkungan lokal (Windows/Mac/Linux) tanpa Redis/MySQL: SQLite + antrean database.
 *
 *   php scripts/setup-local.php "C:\path\client_secret_xxx.json"
 *
 * Aman dijalankan berulang. Rahasia dari file JSON tidak pernah dicetak.
 */
chdir(dirname(__DIR__));

$redirect = 'http://localhost:8000/auth/google/callback';
$jsonPath = $argv[1] ?? null;

if (! file_exists('vendor/autoload.php')) {
    fwrite(STDERR, "Jalankan `composer install` terlebih dahulu.\n");
    exit(1);
}

// 1) .env dari contoh
if (! file_exists('.env')) {
    copy('.env.example', '.env');
    echo "• .env dibuat dari .env.example\n";
}
$env = file_get_contents('.env');

$set = function (string $key, string $value) use (&$env): void {
    $line = $key.'='.$value;
    $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
    $env = preg_match($pattern, $env) ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $env, 1) : rtrim($env)."\n".$line."\n";
};
$remove = function (string $key) use (&$env): void {
    $env = preg_replace('/^#?\s*'.preg_quote($key, '/').'=.*\R?/m', '', $env);
};

// 2) Pengaturan lokal
$set('APP_URL', 'http://localhost:8000');
$set('DB_CONNECTION', 'sqlite');
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $k) {
    $remove($k); // DB_DATABASE sisa akan dianggap nama file SQLite
}
$set('QUEUE_CONNECTION', 'database');
$set('CACHE_STORE', 'database');
$set('SESSION_DRIVER', 'database');
$set('SESSION_SECURE_COOKIE', 'false');
$set('GOOGLE_REDIRECT_URI', $redirect);

// 3) Kredensial Google dari file JSON
if ($jsonPath !== null) {
    if (! is_readable($jsonPath)) {
        fwrite(STDERR, "File tidak ditemukan: {$jsonPath}\n");
        exit(1);
    }
    $web = json_decode(file_get_contents($jsonPath), true)['web'] ?? null;
    if (! $web || empty($web['client_id']) || empty($web['client_secret'])) {
        fwrite(STDERR, "Format file salah: harus JSON client OAuth bertipe 'Web application'.\n");
        exit(1);
    }
    if (! in_array($redirect, $web['redirect_uris'] ?? [], true)) {
        echo "! PERINGATAN: redirect URI di file tidak memuat {$redirect}.\n  Tambahkan di Google Console (Clients) atau login akan gagal dengan redirect_uri_mismatch.\n";
    }
    $set('GOOGLE_CLIENT_ID', $web['client_id']);
    $set('GOOGLE_CLIENT_SECRET', $web['client_secret']);
    echo "• Kredensial Google dimuat dari file (rahasia tidak ditampilkan)\n";
} elseif (! preg_match('/^GOOGLE_CLIENT_SECRET=.+$/m', $env)) {
    echo "! GOOGLE_CLIENT_ID/SECRET belum terisi. Jalankan ulang dengan path file JSON dari Google.\n";
}

file_put_contents('.env', $env);

// 4) SQLite, key, migrasi
if (! file_exists('database/database.sqlite')) {
    touch('database/database.sqlite');
    echo "• database/database.sqlite dibuat\n";
}
if (! preg_match('/^APP_KEY=base64:.+$/m', $env)) {
    passthru(PHP_BINARY.' artisan key:generate --force', $rc);
    $rc === 0 || exit($rc);
}
passthru(PHP_BINARY.' artisan config:clear > '.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
passthru(PHP_BINARY.' artisan migrate --force', $rc);
$rc === 0 || exit($rc);

echo <<<TXT

Siap. Langkah berikutnya (dua jendela terminal di folder ini):

  1) php -d upload_max_filesize=50M -d post_max_size=55M artisan serve
  2) php artisan queue:work --queue=uploads,default

Lalu buka http://localhost:8000  (harus "localhost", bukan 127.0.0.1)

TXT;
