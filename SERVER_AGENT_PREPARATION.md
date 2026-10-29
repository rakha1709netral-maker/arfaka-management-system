# Brief untuk AI Agent di Server Arfaka Tour

Project Laravel ini akan di-host pada server pribadi Linux Mint dengan spesifikasi:

- HP G42
- Intel Core i5 M460 2.53 GHz
- RAM 2 GB + swap 4 GB di HDD
- Storage 500 GB
- Domain: `rakhayuda.my.id`

## Tujuan

Siapkan server untuk menjalankan aplikasi Laravel Arfaka Tour secara production dengan trafik internal ringan. Jangan mengubah source code aplikasi atau menambah fitur bisnis tanpa instruksi.

## Informasi yang harus diaudit terlebih dahulu

Laporkan hasil perintah berikut sebelum melakukan perubahan:

```bash
hostnamectl
cat /etc/os-release
free -h
df -h
swapon --show
php -v
php -m
composer --version
node --version
npm --version
mysql --version || mariadb --version
nginx -v
sudo systemctl --failed
```

Periksa juga:

- Apakah `rakhayuda.my.id` sudah mengarah ke IP publik server.
- Apakah port 80 dan 443 dapat diteruskan dari router ke server.
- Apakah ISP memblokir inbound port 80/443.
- Apakah server memiliki IP publik atau membutuhkan tunnel/reverse proxy.
- Apakah SSH menggunakan key authentication dan bukan password saja.

## Stack yang disarankan

- Nginx
- PHP-FPM 8.4 atau lebih baru
- MariaDB/MySQL
- Composer 2
- Node.js hanya untuk build asset, bukan proses runtime
- Certbot + Let's Encrypt untuk HTTPS

Server RAM 2 GB hanya untuk penggunaan internal ringan. Jangan memasang panel berat atau menjalankan Vite development server di production.

## Persiapan keamanan

1. Jangan pernah meminta atau menampilkan isi `.env`, password database, SMTP password, SSH private key, atau app-password Gmail.
2. Pastikan `.env` tidak dilacak Git:

```bash
git check-ignore -v .env
git ls-files .env
```

3. Buat user deploy non-root dengan akses `sudo` terbatas.
4. Aktifkan firewall hanya untuk SSH, HTTP, dan HTTPS.
5. Pastikan SSH root login dan password login dinonaktifkan setelah key login teruji.
6. Buat backup database dan folder receipt secara berkala.

## Deployment aplikasi

Gunakan repository:

```text
https://github.com/rakha1709netral-maker/arfaka-management-system.git
```

Perintah umum:

```bash
git clone https://github.com/rakha1709netral-maker/arfaka-management-system.git
cd arfaka-management-system
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

Isi `.env` production secara lokal di server:

```env
APP_NAME="Arfaka Tour"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rakhayuda.my.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=arfaka_tour
DB_USERNAME=<database-user>
DB_PASSWORD=<database-password>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

SMTP production harus memakai provider yang benar-benar mengirim email. Mailtrap Sandbox hanya untuk testing dan tidak mengirim ke Gmail nyata.

Lanjutkan:

```bash
php artisan migrate --force
php artisan db:seed --class=SuperAdminSeeder --force
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
php artisan optimize
```

Super admin development tidak boleh dipakai sebagai akun production tanpa mengganti password dan emailnya.

## Nginx dan HTTPS

- Document root harus menunjuk ke folder `public`, bukan root repository.
- Semua request harus diarahkan ke `public/index.php` melalui `try_files`.
- Aktifkan HTTPS Let's Encrypt untuk `rakhayuda.my.id`.
- Pastikan upload file memiliki batas ukuran yang sesuai.
- Jangan expose folder `storage/app` secara langsung.

## Verifikasi wajib setelah deployment

Jalankan dan laporkan hasilnya:

```bash
php artisan about
php artisan route:list --except-vendor
php artisan migrate:status
php artisan test --compact
curl -I https://rakhayuda.my.id/login
```

Uji manual:

- Login super admin.
- Login Tour Leader.
- Guest diarahkan ke login.
- Tour Leader ditolak dari area super admin.
- Pembuatan trip dan assignment berjalan.
- Upload receipt private dapat dilihat user yang berhak.
- Email invitation terkirim melalui SMTP production.
- Logout bekerja.

## Format laporan balik

Kirim kembali:

1. OS dan versi kernel.
2. Versi PHP, PHP-FPM, Composer, MariaDB, Nginx, dan Node.
3. Status DNS, port, HTTPS, dan firewall.
4. Path deployment dan user pemilik file.
5. Status migration dan hasil test.
6. Error yang ditemukan beserta perintah dan output yang sudah disanitasi.
7. Jangan kirim nilai secret atau isi `.env`.

