# Smart e-LPJ SOBE PB PERKEMI

Smart e-LPJ adalah modular monolith Laravel + React untuk pertanggungjawaban kegiatan berbasis `Activity-ID`: mandat, pelaksanaan, data sumber, snapshot LPJ immutable, verifikasi tiga lapis, temuan/CAPA, persetujuan, PB Verified, dan arsip digital.

## Stack

- Laravel 13, PHP 8.3+, Fortify, Sanctum, Spatie Permission, dan Spatie Backup
- React 19, TypeScript, Inertia 3, Tailwind CSS 4, dan Vite
- PostgreSQL sebagai source of truth; UUIDv7 untuk identitas utama
- Redis untuk cache, session, queue, rate limit, lock scheduler, dan cache permission
- object storage privat melalui Laravel Filesystem untuk evidence dan verified pack
- OpenAI Responses API bersifat opsional; aplikasi memakai rules engine lokal saat key tidak tersedia

## Menjalankan lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer run dev
```

Siapkan PostgreSQL dan Redis sesuai `.env`. Untuk evaluasi cepat tanpa layanan tersebut, `.env` lokal dapat memakai SQLite serta driver `database`; konfigurasi produksi yang dianjurkan tetap PostgreSQL + Redis.

## Akun demo

Semua akun seeder memakai password `password`:

- `admin@perkemi.test` — Super Admin
- `pic@perkemi.test` — Owner/PIC
- `finance@perkemi.test` — Finance PIC
- `verifier.admin@perkemi.test`, `verifier.finance@perkemi.test`, `verifier.substantive@perkemi.test`
- `approver@perkemi.test`, `pb@perkemi.test`, `auditor@perkemi.test`

## Quality gates

```bash
vendor/bin/pint --format agent
composer test
npm run types:check
npm run build
composer audit
npm audit --audit-level=high
```

## Keputusan keamanan dan workflow

- Setiap query operasional dibatasi unit melalui policy dan scoped route binding.
- Pengaju, verifier, approver, dan verifier PB dipisahkan pada backend.
- Submission membuat snapshot serta SHA-256 checksum baru; return/resubmit tidak menimpa versi lama.
- Evidence dan archive berada pada disk privat. Setiap unduhan menghasilkan audit event.
- Transaksi hanya masuk melalui endpoint integrasi `/api/v1/integrations/finance/transactions` dengan idempotency key; e-LPJ tidak menjadi ledger baru.
- Audit event bersifat append-only pada model dan tidak memiliki endpoint edit/hapus.
- OpenAI key hanya dibaca server. Structured output tidak pernah mengubah status workflow dan tetap berlabel perlu human review.

## Operasional

Jalankan worker queue dan scheduler pada deployment:

```bash
php artisan queue:work --tries=5 --backoff=30
php artisan schedule:work
```

Scheduler membuat backup database setiap hari, membersihkan backup lama, lalu menjalankan health monitor. Uji restore berkala dengan mengekstrak ZIP terbaru pada lingkungan terisolasi, memulihkan dump PostgreSQL, menjalankan `php artisan migrate:status`, dan melakukan smoke test tanpa mengarah ke storage produksi.

Sebelum produksi, organisasi masih perlu menetapkan authority matrix nilai anggaran, sumber SSO/ledger nyata, kebijakan klasifikasi dan retensi, checklist per jenis kegiatan, serta format verified pack/QR final sebagaimana dicatat di `md/flow.md`.
# sobe-elpj-perkemi
