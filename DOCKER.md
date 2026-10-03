# Deploy dengan Docker

Satu-satunya prasyarat di server: **Docker + Docker Compose**. PHP, Nginx, PostgreSQL, dan scheduler sudah ada di dalam container.

## Instalasi pertama

```sh
git clone <URL_REPOSITORY> asset-kantorku && cd asset-kantorku
cp .env.docker.example .env
nano .env                # isi APP_URL dan DB_PASSWORD (set RUN_SEED=true bila perlu data awal)
docker compose up -d --build
```

Saat container start, otomatis: menunggu database → `migrate --force` → (opsional) seed → `optimize` → menjalankan Nginx, PHP-FPM, dan `schedule:work`.

Jika `APP_KEY` dikosongkan, key dibuat sekali dan disimpan di volume `storage` (`storage/app/.app_key`). Jangan hapus volume tersebut — atau salin key-nya ke `.env` agar aman.

Setelah login pertama dengan akun seed (`password`), ganti password dan kembalikan `RUN_SEED=false`.

## Update

```sh
git pull --ff-only
docker compose up -d --build
```

## Perintah berguna

```sh
docker compose logs -f app                      # log aplikasi
docker compose exec app php artisan <perintah>  # artisan
docker compose exec db pg_dump -U assetkantorku -Fc asset_kantorku > backup.dump
```

## Catatan

- Data persisten ada di volume `pgdata` (database) dan `storage` (lampiran, key). Backup keduanya.
- HTTPS: taruh reverse proxy (Caddy/Nginx/Traefik/Cloudflare) di depan port `APP_PORT`.
- Tanpa Compose (DB eksternal): `docker build -t asset-kantorku .` lalu `docker run -d -p 80:80 --env-file .env -v asset_storage:/var/www/html/storage asset-kantorku` dengan `DB_HOST` dkk. di `.env`.
