# Deployment ke VPS

Panduan ini menjelaskan deployment Asset Kantorku pada VPS Ubuntu 24.04 dengan Nginx, PHP-FPM, dan PostgreSQL. Sesuaikan versi paket dengan OS yang dipakai, tetapi pertahankan persyaratan aplikasi: PHP 8.3 atau lebih baru dan PostgreSQL 13 atau lebih baru.

> **Keamanan akun awal:** seeder proyek memuat data demo serta akun yang password awalnya `password`. Jangan biarkan aplikasi dapat diakses publik dengan kredensial tersebut. Batasi akses selama bootstrap, ganti password `sysadmin@kantorku.test`, lalu nonaktifkan akun demo lainnya sebelum membuka akses umum.

## 1. Prasyarat VPS

Untuk instalasi kecil, mulai dengan 2 vCPU, RAM 2 GB, dan SSD 25 GB atau lebih. RAM 4 GB lebih nyaman jika database dan PHP berjalan pada VPS yang sama. Siapkan domain yang diarahkan ke alamat IP VPS.

Pasang layanan dan ekstensi PHP yang diperlukan:

```sh
sudo apt update
sudo apt install -y nginx postgresql postgresql-contrib \
  php8.3-cli php8.3-fpm php8.3-pgsql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl \
  git unzip
```

Pasang Composer 2 dari instruksi resmi Composer dan Node.js 22 (minimal 20.19 atau 22.12 untuk Vite 8). Verifikasi versi dan ekstensi:

```sh
php -v
composer --version
node --version
npm --version
php -m | grep -E 'bcmath|ctype|curl|dom|fileinfo|gd|intl|mbstring|openssl|pdo_pgsql|xml|zip'
```

Batasi firewall ke SSH dan web:

```sh
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

Jangan membuka port PostgreSQL `5432` ke internet. Jika database berada di server lain, batasi aksesnya ke alamat IP aplikasi.

## 2. Buat database PostgreSQL

Buat database dan role aplikasi. `\password` meminta password secara interaktif agar password tidak tersimpan di riwayat shell:

```sh
sudo -u postgres psql
```

Di prompt PostgreSQL:

```sql
CREATE ROLE assetkantorku LOGIN;
\password assetkantorku
CREATE DATABASE assetkantorku OWNER assetkantorku;
\q
```

Migration proyek memerlukan ekstensi `btree_gist` untuk exclusion constraint. Migration mencoba mengaktifkannya sendiri. Jika role aplikasi tidak diizinkan memasang ekstensi tersebut, jalankan sekali sebagai administrator PostgreSQL:

```sh
sudo -u postgres psql -d assetkantorku -c 'CREATE EXTENSION IF NOT EXISTS btree_gist;'
```

## 3. Ambil source code

Buat direktori aplikasi dan clone repository open source ini. Ganti URL di bawah dengan URL repository yang sebenarnya:

```sh
sudo mkdir -p /var/www/asset-kantorku
sudo chown "$USER":www-data /var/www/asset-kantorku
cd /var/www/asset-kantorku
git clone <URL_REPOSITORY> .
```

Jangan menaruh `.env`, kredensial, backup database, atau kunci rahasia ke repository.

## 4. Konfigurasi environment

Buat file environment lokal dari template:

```sh
cp .env.example .env
nano .env
```

Atur nilai produksi berikut dan sesuaikan nilai database:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.example

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=assetkantorku
DB_USERNAME=assetkantorku
DB_PASSWORD=<PASSWORD_DATABASE>
```

Pastikan `APP_KEY` kosong untuk instalasi pertama, kemudian generate sekali:

```sh
php artisan key:generate
```

Simpan `APP_KEY` dengan aman. Jangan generate ulang saat update: key lama diperlukan untuk membaca data yang dienkripsi dengan key tersebut. File `.env` sudah diabaikan Git; jangan commit atau mengirim isinya melalui chat/log.

## 5. Install dependency, build, dan migration

Dari direktori proyek:

```sh
composer install --no-dev --optimize-autoloader --no-interaction
npm install --ignore-scripts
npm run build
php artisan migrate --force
```

Repository saat panduan ini ditulis belum menyertakan `package-lock.json`, sehingga gunakan `npm install`, bukan `npm ci`. Untuk build yang reproducible, proyek sebaiknya menambahkan dan memelihara lockfile npm.

`migrate --force` hanya menerapkan migration yang belum jalan. **Jangan gunakan `migrate:fresh` pada produksi** karena perintah tersebut menghapus semua tabel dan data.

### Bootstrap data dan akun admin

Migration membuat skema, tetapi role, permission, akun, dan data awal dimasukkan oleh seeder. Seeder yang tersedia adalah seeder demo:

```sh
php artisan db:seed --force
```

Jalankan seeder hanya pada database kosong dan saat akses situs masih dibatasi. Seed membuat akun demo dengan password `password`, termasuk `sysadmin@kantorku.test`. Setelah seed:

1. Masuk sebagai `sysadmin@kantorku.test` dengan password sementara `password`.
2. Ubah password melalui menu **Profil Saya**. Gunakan password unik yang memenuhi kebijakan aplikasi.
3. Melalui administrasi pengguna, nonaktifkan atau hapus semua akun demo lain yang tidak diperlukan.
4. Tinjau dan hapus data contoh sebelum memakai sistem dengan data nyata.
5. Hapus pembatasan akses sementara hanya setelah langkah di atas selesai.

Untuk server publik atau data nyata, solusi yang disarankan adalah menyiapkan seeder produksi terpisah yang membuat role/permission dan admin dengan kredensial unik, tanpa akun maupun data demo. Jangan menjalankan seeder demo berulang kali sebagai proses update.

## 6. Permission file

Nginx/PHP-FPM perlu membaca source code, tetapi hanya direktori runtime yang perlu dapat ditulis:

```sh
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Jangan memberi permission tulis ke seluruh source code untuk user `www-data`.

## 7. Konfigurasi Nginx

Buat `/etc/nginx/sites-available/asset-kantorku`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name domain-anda.example www.domain-anda.example;
    root /var/www/asset-kantorku/public;
    index index.php;

    charset utf-8;
    client_max_body_size 10m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Ganti domain dan sesuaikan socket PHP-FPM dengan versi yang terpasang. Aktifkan situs dan periksa konfigurasi:

```sh
sudo ln -s /etc/nginx/sites-available/asset-kantorku /etc/nginx/sites-enabled/asset-kantorku
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable --now php8.3-fpm postgresql nginx
```

Arahkan document root **hanya** ke folder `public/`, bukan root repository. Jangan gunakan `php artisan serve` sebagai web server produksi.

## 8. Aktifkan HTTPS dan cache produksi

Pasang Certbot dari paket Ubuntu, lalu terbitkan sertifikat untuk domain:

```sh
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d domain-anda.example -d www.domain-anda.example
```

Setelah konfigurasi produksi benar:

```sh
php artisan optimize
```

Buka situs melalui HTTPS dan periksa log jika ada masalah:

```sh
tail -f storage/logs/laravel.log
```

Pastikan `APP_DEBUG=false` sebelum situs dapat diakses umum.

## 9. Update aplikasi

Sebelum update, buat backup database dan file upload. Dari direktori aplikasi:

```sh
php artisan down
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm install --ignore-scripts
npm run build
php artisan migrate --force
php artisan optimize
php artisan up
sudo systemctl reload php8.3-fpm nginx
```

Jangan menjalankan `key:generate`, `db:seed`, atau `migrate:fresh` sebagai bagian dari update rutin. Periksa release dan migration sebelum menjalankan update pada database produksi.

## 10. Backup dan pemulihan

Jadwalkan backup PostgreSQL dan simpan salinannya di luar VPS. Contoh backup manual:

```sh
sudo -u postgres pg_dump -Fc assetkantorku > assetkantorku-$(date +%F).dump
```

Uji pemulihan backup secara berkala pada database terpisah. Salin juga file upload dari `storage/app` jika aplikasi menyimpan lampiran di disk lokal. Snapshot pada VPS yang sama bukan pengganti backup di lokasi terpisah.

## Checklist sebelum dibuka ke publik

- [ ] DNS domain mengarah ke VPS dan HTTPS aktif.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, dan `APP_URL` menggunakan HTTPS.
- [ ] `.env` dan `APP_KEY` tidak masuk Git atau web root publik.
- [ ] Password database dan password sysadmin sudah unik dan kuat.
- [ ] Akun demo lain dinonaktifkan/dihapus dan data demo ditinjau.
- [ ] Migration sukses; `migrate:fresh` tidak pernah dijalankan pada database produksi.
- [ ] Port PostgreSQL tidak terbuka ke internet.
- [ ] Direktori `storage` dan `bootstrap/cache` writable oleh PHP-FPM.
- [ ] Backup database serta lampiran diuji pemulihannya.
