# 🔐 CyberSecure — Backend API (Laravel)

Backend REST API untuk aplikasi CyberSecure, dibangun dengan Laravel + Sanctum (token auth) + SQLite.

---

## ⚡ Quick Setup (Wajib diikuti urutan ini!)

### 1. Clone & install dependencies

```bash
git clone <repo-url> cybersecure-backend
cd cybersecure-backend
composer install
```

### 2. Buat file `.env`

```bash
cp .env.example .env
php artisan key:generate
```

> ⚠️ File `.env` tidak ikut di-commit. Kamu **wajib** membuatnya dari `.env.example`.

### 3. Buat file database SQLite

File `database/database.sqlite` **tidak ikut di-commit** (di-gitignore). Buat dulu:

**Windows (PowerShell):**
```powershell
New-Item -ItemType File -Path database\database.sqlite
```

**Mac / Linux:**
```bash
touch database/database.sqlite
```

### 4. Jalankan migrasi + seeder

```bash
php artisan migrate --seed
```

> Perintah ini akan membuat semua tabel dan mengisi data mock (marketplace, transaksi, notifikasi, anomali).
> 
> Akun demo yang dibuat otomatis:
> - **Email:** `test@gmail.com`
> - **Password:** `password`

### 5. Jalankan server

```bash
php artisan serve
```

Server berjalan di `http://127.0.0.1:8000`.

---

## 🔗 Hubungkan ke Frontend

Pastikan frontend (`cybersecure-frontend`) menggunakan base URL:
```
http://127.0.0.1:8000/api
```

Frontend dev server harus berjalan di `http://localhost:5173` agar CORS tidak error.

---

## ❓ Troubleshooting

### Semua endpoint selain login/register error / data kosong

Penyebab paling umum:

| Masalah | Solusi |
|---|---|
| File `.env` tidak ada | `cp .env.example .env` → `php artisan key:generate` |
| File `database.sqlite` tidak ada | Buat file kosong (lihat langkah 3) |
| Migrasi belum dijalankan | `php artisan migrate` |
| Seeder belum dijalankan | `php artisan db:seed` |
| Ingin reset total | `php artisan migrate:fresh --seed` |

### Error `SQLSTATE[HY000]: General error: 14 unable to open database file`

File `database/database.sqlite` belum dibuat. Jalankan langkah 3.

### Error `419 CSRF token mismatch` atau token expired

```bash
php artisan config:clear
php artisan cache:clear
```

### Akun yang sudah ada tidak punya data (transaksi/notifikasi kosong)

Jalankan seeder tanpa reset data:
```bash
php artisan db:seed
```

---

## 📁 Struktur Penting

```
app/
  Http/Controllers/API/     ← Semua endpoint API
  Services/UserActivitySeeder.php  ← Logic seed data per user
database/
  migrations/               ← Skema tabel
  seeders/                  ← Data awal / mock data
routes/
  api.php                   ← Semua route API
```

---

## 🛡️ Auth

Menggunakan **Laravel Sanctum**. Setelah login, frontend menerima `token` yang harus dikirim di header:

```
Authorization: Bearer <token>
```

Semua endpoint selain `/login`, `/register`, dan `/forgot-password` membutuhkan token ini.

---

## 📋 Endpoint Utama

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/login` | Login, dapat token |
| POST | `/api/register` | Daftar akun baru |
| GET | `/api/user` | Info user login *(auth)* |
| GET | `/api/marketplaces` | Daftar marketplace terhubung *(auth)* |
| GET | `/api/transactions` | Log transaksi *(auth)* |
| GET | `/api/transactions/summary` | Ringkasan dashboard *(auth)* |
| GET | `/api/anomalies` | Daftar anomali *(auth)* |
| GET | `/api/anomalies/metrics` | Metrik keamanan *(auth)* |
| GET | `/api/notifications` | Notifikasi *(auth)* |
| GET | `/api/reports/monthly` | Laporan bulanan *(auth)* |
