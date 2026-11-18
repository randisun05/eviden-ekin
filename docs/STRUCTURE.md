# Evident Kinerja — Struktur Proyek

```
eviden-ekin/
├── app/
│   ├── Exceptions/GoogleDriveException.php   # reauthRequired() = jangan retry job
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/            # GoogleAuthController (redirect/callback Socialite)
│   │   │   ├── Public/          # EvidentViewerController (landing /v/{short_code}, guest)
│   │   │   ├── PeriodController, RhkController, EvidentController (Inertia)
│   │   ├── Middleware/          # HandleInertiaRequests, EnsureDriveConnected
│   │   └── Requests/            # FormRequest validasi upload
│   ├── Jobs/                    # ProcessGdriveUpload (queue: uploads)
│   ├── Models/                  # User, Period, Rhk, Evident, DriveFolder (+ Concerns/HasUuidV4)
│   ├── Policies/                # kepemilikan Period/Rhk/Evident per user
│   └── Services/
│       ├── Google/GoogleDriveService.php   # OAuth refresh, folder engine, resumable upload
│       └── Evident/             # LinkGenerator (direct + shortlink), ReadinessChecker
├── config/                      # evident.php (konfigurasi domain), services.php (google), horizon.php
├── database/migrations/         # users, cache, jobs, periods, rhks, evidents, drive_folders
├── resources/js/
│   ├── Pages/                   # Auth, Periods, Rhks, Public (Inertia pages)
│   ├── Components/ui/           # Shadcn Vue
│   ├── Layouts/  composables/  lib/
│   └── app.ts                   # createInertiaApp
├── routes/                      # web.php (auth+app), public.php (/v/{code})
├── storage/app/tmp/             # staging upload sebelum dikirim ke Drive
├── deploy/                      # nginx.conf, supervisor/horizon.conf, cloudflared config
└── tests/
```

## Keputusan desain
- **UUID v4** untuk `periods`, `rhks`, `evidents` (trait `HasUuidV4`; bawaan Laravel 12 adalah v7). `users.id` tetap bigint.
- **Scope Drive = `drive.file`**: aplikasi hanya melihat file/folder yang ia buat sendiri (least privilege).
- **Cache folder 2 lapis**: Redis (L1, TTL) → tabel `drive_folders` (L2, permanen) → Drive API.
- **`evidents.access_pin` disimpan sebagai hash** (cast `hashed`), bukan plaintext.
- Token Google dienkripsi di DB via cast `encrypted` (APP_KEY).
