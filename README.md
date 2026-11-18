# Evident Kinerja

Repositori bukti kerja ASN per Rencana Hasil Kerja (RHK) per periode penilaian. Berkas disimpan di **Google Drive milik pengguna sendiri**; aplikasi menghasilkan tautan langsung dan shortlink landing page yang siap ditempel ke e-Kinerja.

Stack: Laravel 12 (PHP 8.3) · Vue 3 + Inertia + Tailwind 4 + shadcn-vue · MySQL 8 · Redis + Horizon · Google Drive API (OAuth per pengguna).

## Pengembangan lokal

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# isi DB_*, GOOGLE_CLIENT_ID/SECRET di .env, lalu:
php artisan migrate
npm run dev &  php artisan serve &  php artisan horizon
```

```bash
php artisan test        # 50 tes (Drive dipalsukan; tidak butuh jaringan)
npm run build           # termasuk typecheck vue-tsc
```

## Dokumentasi
- [Struktur proyek & keputusan desain](docs/STRUCTURE.md)
- [Panduan deploy](docs/DEPLOY.md)
