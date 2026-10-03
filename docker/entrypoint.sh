#!/bin/sh
set -e

cd /var/www/html

artisan() {
    su-exec www-data php artisan "$@"
}

# Volume storage bisa kosong/lama: pastikan struktur direktori & permission benar
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# APP_KEY: jika tidak diisi, generate sekali lalu simpan di volume agar tetap sama saat restart/update
if [ -z "$APP_KEY" ]; then
    KEY_FILE=storage/app/.app_key
    if [ ! -s "$KEY_FILE" ]; then
        echo "[entrypoint] APP_KEY kosong, membuat key baru di $KEY_FILE"
        echo "base64:$(head -c 32 /dev/urandom | base64)" > "$KEY_FILE"
        chown www-data:www-data "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Menunggu database & menjalankan migration..."
    tries=0
    until artisan migrate --force --no-interaction; do
        tries=$((tries + 1))
        if [ "$tries" -ge "${DB_WAIT_TRIES:-30}" ]; then
            echo "[entrypoint] Database tidak bisa dihubungi, berhenti." >&2
            exit 1
        fi
        sleep 2
    done

    # Seeder aman diulang: otomatis dilewati jika data RBAC sudah ada
    if [ "${RUN_SEED:-false}" = "true" ]; then
        artisan db:seed --force --no-interaction
    fi
fi

artisan optimize

exec "$@"
