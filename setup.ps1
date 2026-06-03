# ============================================================
# CyberSecure Backend - Auto Setup Script (Windows PowerShell)
# Jalankan: .\setup.ps1
# ============================================================

Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "   CyberSecure Backend - Auto Setup" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: composer install
Write-Host "[1/5] Menginstall PHP dependencies..." -ForegroundColor Yellow
composer install --no-interaction
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: composer install gagal. Pastikan Composer sudah terinstall." -ForegroundColor Red
    exit 1
}

# Step 2: Buat .env
Write-Host ""
Write-Host "[2/5] Menyiapkan file .env..." -ForegroundColor Yellow
if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "  .env dibuat dari .env.example" -ForegroundColor Green
} else {
    Write-Host "  .env sudah ada, dilewati." -ForegroundColor Gray
}

# Step 3: Generate APP_KEY
Write-Host ""
Write-Host "[3/5] Generate application key..." -ForegroundColor Yellow
php artisan key:generate
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: php artisan key:generate gagal." -ForegroundColor Red
    exit 1
}

# Step 4: Buat file SQLite
Write-Host ""
Write-Host "[4/5] Menyiapkan database SQLite..." -ForegroundColor Yellow
if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" | Out-Null
    Write-Host "  database.sqlite dibuat." -ForegroundColor Green
} else {
    Write-Host "  database.sqlite sudah ada, dilewati." -ForegroundColor Gray
}

# Step 5: Migrate + Seed
Write-Host ""
Write-Host "[5/5] Menjalankan migrasi dan seeder..." -ForegroundColor Yellow
php artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: migrate/seed gagal. Coba jalankan manual:" -ForegroundColor Red
    Write-Host "  php artisan migrate:fresh --seed" -ForegroundColor White
    exit 1
}

Write-Host ""
Write-Host "=========================================" -ForegroundColor Green
Write-Host "   Setup selesai! Menjalankan server..." -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Akun demo:" -ForegroundColor Cyan
Write-Host "  Email    : test@gmail.com" -ForegroundColor White
Write-Host "  Password : password" -ForegroundColor White
Write-Host ""
Write-Host "Atau daftar akun baru — data mock akan otomatis ter-generate." -ForegroundColor Gray
Write-Host ""

php artisan serve
