# Menjalankan Evident Kinerja di Windows (uji lokal)

Versi lokal memakai SQLite dan antrean database (tanpa Redis/MySQL/Horizon). Alur upload ke Google Drive tetap sama.

## Persiapan (sekali)
1. Pasang **Laravel Herd** (herd.laravel.com): berisi PHP 8.3, Composer, Node.
2. Pasang **Git for Windows**.
3. Siapkan file JSON OAuth dari Google Console (Clients → klik client → Download JSON). Redirect URI client harus `http://localhost:8000/auth/google/callback`.
4. Tutup lalu buka PowerShell baru.

## Pasang
```powershell
git clone https://github.com/randisun05/eviden-ekin.git
cd eviden-ekin
git checkout claude/gracious-brahmagupta-s7a9k2
composer install
npm install
npm run build
php scripts/setup-local.php "C:\Users\ANDA\Downloads\client_secret_XXXX.json"
```

Skrip terakhir membuat `.env`, memasukkan kredensial dari file JSON (tanpa menampilkan rahasia), membuat database SQLite, dan menjalankan migrasi.

## Jalankan (dua jendela PowerShell di folder yang sama)
```powershell
php -d upload_max_filesize=50M -d post_max_size=95M -d max_file_uploads=20 artisan serve
```
```powershell
php artisan queue:work --queue=uploads,default
```

## Uji
Buka `http://localhost:8000` (harus `localhost`, bukan `127.0.0.1`).
1. **Masuk dengan Google** → di layar "Google hasn't verified this app" pilih *Advanced → Go to … (unsafe)* → setujui akses Drive.
2. Buat periode → tambah RHK → unggah PDF kecil.
3. Status berubah *Mengunggah…* → *Siap*. Folder `EvidentKinerja/<tahun>/<periode>/<RHK>` muncul di Drive.
4. Salin shortlink RHK → buka di jendela penyamaran → coba *Pratinjau*, *Unduh*, lalu atur PIN dan tanggal berlaku.
5. Hapus satu bukti → file hilang dari Drive (dikerjakan worker, beberapa detik).

## Jika gagal
Kumpulkan: pesan di layar, 20 baris terakhir `storage\logs\laravel.log`, dan output jendela worker. Jangan kirim isi `.env`.

| Gejala | Penyebab umum |
|---|---|
| `redirect_uri_mismatch` | Membuka `127.0.0.1` bukan `localhost`, atau redirect URI di Console beda |
| `access_denied` / "app not verified" tidak bisa lanjut | Email belum ada di Audience → Test users (jika status Testing) |
| Status tetap *Mengunggah…* | Jendela worker (`queue:work`) tidak berjalan |
| Unggah gagal dengan pesan akses dicabut | Login ulang; pastikan kotak akses Drive dicentang |
| "Login Google gagal"; log berisi `cURL error 60: SSL certificate ... unable to get local issuer certificate` | PHP (mis. XAMPP) belum punya CA bundle. Tambahkan ke perintah `artisan serve` **dan** `queue:work`: `-d curl.cainfo=C:\xampp\apache\bin\curl-ca-bundle.crt -d openssl.cafile=C:\xampp\apache\bin\curl-ca-bundle.crt` (atau set keduanya di php.ini) |
| Halaman 404/500 aneh padahal rute ada | Port 8000 sudah dipakai aplikasi lain (`netstat -ano`). Hentikan yang lain, lalu jalankan ulang |
| `composer install` meminta `ext-pcntl`/`ext-posix` | Hanya dibutuhkan Laravel Horizon di Linux (produksi). Lokal: `composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` |
| Berkas > 2 MB gagal, atau banyak berkas sekaligus gagal | `artisan serve` dijalankan tanpa opsi `-d upload_max_filesize=50M -d post_max_size=95M -d max_file_uploads=20` |
