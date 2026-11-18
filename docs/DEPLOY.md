# Panduan Deploy — Evident Kinerja

Target: Ubuntu 24.04, Nginx, PHP 8.3-FPM, MySQL 8, Redis, Supervisor, Cloudflare Tunnel.

```
Internet ─▶ Cloudflare ─▶ cloudflared (tunnel) ─▶ nginx 127.0.0.1:8080 ─▶ php-fpm ─▶ Laravel
                                                                     MySQL ◀─┤├─▶ Redis ◀─ Horizon (supervisor)
                                                                                         └─▶ Google Drive API
```

Nginx hanya mendengarkan di loopback, jadi satu-satunya jalur masuk adalah tunnel. Tidak ada port yang dibuka ke internet.

## 1. Paket sistem

```bash
sudo apt update
sudo apt install -y nginx mysql-server redis-server supervisor git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath
# Horizon butuh ekstensi pcntl & posix (sudah ada di php8.3-cli Ubuntu). Cek:
php -m | grep -E "pcntl|posix"
# Composer & Node 22 (nodesource atau nvm) dipasang terpisah.
```

## 2. MySQL & Redis

```sql
CREATE DATABASE evident_kinerja CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'evident'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL ON evident_kinerja.* TO 'evident'@'localhost';
```

`/etc/redis/redis.conf`:

```
bind 127.0.0.1 -::1
requirepass GANTI_PASSWORD_REDIS
maxmemory 512mb
maxmemory-policy noeviction     # WAJIB: antrean tidak boleh ter-evict. Jangan pakai allkeys-lru.
```

## 3. Google OAuth (sekali saja)

1. Google Cloud Console → buat project → **APIs & Services → Library → Google Drive API → Enable**.
2. **OAuth consent screen**: tipe *External*, isi nama aplikasi, email, dan domain `evident.domain.com`.
3. **Scopes**: tambahkan `.../auth/drive.file` (selain `openid`, `email`, `profile`). Scope ini hanya memberi akses ke file/folder yang dibuat aplikasi.
4. **Publishing status → In production.** Jika dibiarkan *Testing*, **refresh token kedaluwarsa setiap 7 hari** dan semua pengguna harus login ulang; pengguna di luar daftar test user juga tidak bisa masuk.
5. **Credentials → Create OAuth client ID → Web application**. Authorized redirect URI: `https://evident.domain.com/auth/google/callback`.
6. Salin Client ID dan Secret ke `.env`.

Catatan: kebutuhan verifikasi Google bergantung pada klasifikasi scope dan jumlah pengguna saat Anda mengajukan. Periksa pesan di layar consent screen Anda sendiri, jangan mengandalkan asumsi.

## 4. Aplikasi

```bash
sudo mkdir -p /var/www/evident-kinerja /var/log/evident
sudo chown $USER:www-data /var/www/evident-kinerja
git clone <URL-REPO> /var/www/evident-kinerja && cd /var/www/evident-kinerja
git checkout <branch-produksi>

cp deploy/.env.production.example .env && nano .env      # isi semua nilai
composer install --no-dev --optimize-autoloader
php artisan key:generate --force

# Izin tulis untuk www-data (php-fpm & horizon berjalan sebagai www-data)
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo chown www-data:www-data /var/log/evident

php artisan migrate --force
```

> **Cadangkan `APP_KEY`** (di password manager). Token Google di database dienkripsi dengan key ini. Jika hilang, token tidak bisa didekripsi dan semua pengguna harus login ulang.

## 5. PHP-FPM, Nginx, Supervisor

```bash
sudo cp deploy/php/evident.ini /etc/php/8.3/fpm/conf.d/90-evident.ini
sudo cp deploy/nginx/evident-kinerja.conf /etc/nginx/sites-available/evident-kinerja
sudo ln -s /etc/nginx/sites-available/evident-kinerja /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
# edit server_name di berkas nginx menjadi domain Anda
sudo nginx -t && sudo systemctl reload nginx && sudo systemctl reload php8.3-fpm

sudo cp deploy/supervisor/evident-kinerja.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status          # evident-horizon & evident-scheduler harus RUNNING
```

**Batas upload harus sama di tiga tempat:** `EVIDENT_MAX_UPLOAD_MB` (50) = `upload_max_filesize` PHP (50M) < `client_max_body_size` Nginx (55m). Cloudflare membatasi body request 100 MB pada paket gratis dan Pro.

## 6. Cloudflare Tunnel

```bash
# pasang cloudflared dari repo resmi Cloudflare, lalu:
cloudflared tunnel login
cloudflared tunnel create evident-kinerja
cloudflared tunnel route dns evident-kinerja evident.domain.com
sudo mkdir -p /etc/cloudflared && sudo cp deploy/cloudflared/config.yml /etc/cloudflared/config.yml
# isi <TUNNEL_ID> dan salin credentials json ke /etc/cloudflared/
sudo cloudflared service install && sudo systemctl status cloudflared
```

## 7. Verifikasi

```bash
curl -s https://evident.domain.com/up                          # 200
curl -sI https://evident.domain.com/.env | head -1             # 403/404, bukan 200
php artisan schedule:list                                      # housekeeping, prune-failed, horizon:snapshot
```

Lalu uji manual: login Google → buat periode → RHK → unggah PDF → status berubah menjadi *Siap* → buka link Drive → buka shortlink `/v/{kode}` di jendela penyamaran → pratinjau PDF tampil.
Dashboard antrean: `https://evident.domain.com/horizon` (hanya email di `EVIDENT_ADMIN_EMAILS`).

## 8. Deploy berikutnya

```bash
cd /var/www/evident-kinerja && ./deploy/deploy.sh
```

Skrip ini: menarik kode (ff-only), install dependensi, build frontend, mode maintenance, migrate, cache config/route/view, `horizon:terminate` (job upload yang berjalan diselesaikan dulu), reload php-fpm. Supervisor otomatis menjalankan ulang Horizon dengan kode baru.

## 9. Operasional

| Kebutuhan | Perintah |
|---|---|
| Log aplikasi | `storage/logs/laravel-*.log` |
| Log Horizon / scheduler | `/var/log/evident/horizon.log`, `scheduler.log` |
| Job gagal | `php artisan queue:failed` (dihapus otomatis setelah 7 hari) |
| Bersih-bersih manual | `php artisan evident:housekeeping --dry-run` |
| Cadangan | dump MySQL harian + `APP_KEY`. File bukti ada di Drive masing-masing pengguna, bukan di server. |

`evident:housekeeping` (harian 03:10) menghapus berkas staging yang lebih tua dari 48 jam dan menandai upload `processing` yang macet sebagai `failed` agar pengguna bisa mengunggah ulang.

## 10. Pemecahan masalah

- **Semua upload `processing` terus-menerus**: Horizon mati. `sudo supervisorctl status`, lihat `/var/log/evident/horizon.log`.
- **Upload besar jalan dua kali**: `REDIS_QUEUE_RETRY_AFTER` lebih kecil dari timeout job (900).
- **413 saat unggah**: batas upload tidak sama di tiga tempat (bagian 5).
- **500 di semua halaman setelah deploy**: izin `storage/` atau `bootstrap/cache/`, atau `php artisan config:cache` dijalankan sebelum `.env` benar.
- **Pengguna harus login ulang tiap minggu**: consent screen masih berstatus *Testing* (bagian 3, langkah 4).
- **Kesalahan `redirect_uri_mismatch`**: `GOOGLE_REDIRECT_URI` dan URI di Google Console tidak persis sama (https, tanpa slash akhir).
- **Rate limit PIN tidak bekerja per pengunjung**: pastikan Nginx menerima `CF-Connecting-IP` (tunnel mengirimnya) dan bagian `set_real_ip_from` tidak diubah.

## Daftar periksa keamanan
- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`
- [ ] Redis: `requirepass` + `bind` loopback + `noeviction`
- [ ] MySQL hanya di localhost, user khusus aplikasi
- [ ] Nginx hanya di `127.0.0.1:8080`; firewall menutup semua port masuk selain SSH
- [ ] `/horizon` hanya untuk email admin
- [ ] `APP_KEY` tercadang di tempat terpisah
