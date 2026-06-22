#!/usr/bin/env bash
# Human QR Menü — ilk kurulum (GitHub clone sonrası)
# Kullanım:
#   cd /www/wwwroot/humansocialpeople.com
#   cp .env.production.example .env && nano .env
#   bash deploy/install-production.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENV_TEMPLATE=".env.production.example"
ENV_FILE=".env"

echo "=========================================="
echo " Human QR Menü — production kurulum"
echo " Dizin: $ROOT"
echo "=========================================="

# ── .env ──
if [[ ! -f "$ENV_FILE" ]]; then
    if [[ -f "$ENV_TEMPLATE" ]]; then
        cp "$ENV_TEMPLATE" "$ENV_FILE"
        echo "==> $ENV_FILE oluşturuldu ($ENV_TEMPLATE kopyalandı)."
        echo "    Düzenleyin: nano .env  (DB_PASSWORD, APP_URL, REVERB_HOST)"
        echo "    Sonra scripti tekrar çalıştırın."
        exit 1
    else
        echo "HATA: $ENV_FILE yok ve $ENV_TEMPLATE bulunamadı."
        exit 1
    fi
fi

if grep -q '^DB_PASSWORD=DEGISTIRIN' "$ENV_FILE" 2>/dev/null; then
    echo "HATA: .env içinde DB_PASSWORD hâlâ DEGISTIRIN. nano .env ile güncelleyin."
    exit 1
fi

# aaPanel: localhost → Unix socket → "No such file or directory" hatası
if grep -qE '^DB_HOST=(localhost|LOCALHOST)' "$ENV_FILE" 2>/dev/null; then
    echo "==> DB_HOST localhost → 127.0.0.1 (aaPanel MySQL TCP bağlantısı)"
    sed -i 's|^DB_HOST=.*|DB_HOST=127.0.0.1|' "$ENV_FILE"
fi

if grep -q '^APP_URL=https://humansocialpeople.com' "$ENV_FILE" && [[ "${SKIP_URL_CHECK:-0}" != "1" ]]; then
    echo "NOT: APP_URL varsayılan. Kendi domaininizi .env'de doğruladınız mı?"
fi

check_mysql_connection() {
    php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    Illuminate\Support\Facades\DB::connection()->getPdo();
    exit(0);
} catch (Throwable \$e) {
    fwrite(STDERR, \$e->getMessage());
    exit(1);
}
" 2>&1
}

# ── Composer (artisan için önce vendor) ──
if ! command -v composer >/dev/null 2>&1; then
    echo "HATA: composer bulunamadı. aaPanel veya https://getcomposer.org"
    exit 1
fi

echo "==> composer install (production)..."
export COMPOSER_ALLOW_SUPERUSER="${COMPOSER_ALLOW_SUPERUSER:-1}"
composer install --no-dev --optimize-autoloader --no-interaction

# ── APP_KEY ──
if ! grep -q '^APP_KEY=base64:' "$ENV_FILE" 2>/dev/null; then
    echo "==> APP_KEY üretiliyor..."
    php artisan key:generate --force
fi

# ── Reverb anahtarları (boşsa otomatik) ──
if grep -qE '^REVERB_APP_KEY=$' "$ENV_FILE" || grep -qE '^REVERB_APP_SECRET=$' "$ENV_FILE"; then
    echo "==> Reverb anahtarları üretiliyor..."
    RKEY="$(openssl rand -hex 16 2>/dev/null || head -c 32 /dev/urandom | xxd -p -c 32)"
    RSEC="$(openssl rand -hex 16 2>/dev/null || head -c 32 /dev/urandom | xxd -p -c 32)"
    sed -i "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=${RKEY}|" "$ENV_FILE"
    sed -i "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=${RSEC}|" "$ENV_FILE"
    php artisan config:clear
fi

# ── MySQL bağlantı testi (migrate öncesi) ──
echo "==> MySQL bağlantı testi..."
if ! DB_ERR="$(check_mysql_connection)"; then
    echo ""
    echo "HATA: MySQL'e bağlanılamadı."
    echo "  $DB_ERR"
    echo ""
    echo "Kontrol listesi:"
    echo "  1. .env → DB_HOST=127.0.0.1  (localhost KULLANMAYIN)"
    echo "  2. aaPanel → Database → human_menu + kullanıcı oluşturuldu mu?"
    echo "  3. DB_PASSWORD aaPanel'deki şifre ile aynı mı?"
    echo "  4. MySQL çalışıyor mu: /etc/init.d/mysqld status"
    echo ""
    echo "Düzelttikten sonra:"
    echo "  php artisan config:clear"
    echo "  php artisan migrate --force --seed"
    exit 1
fi
echo "    MySQL OK"

# ── Node / Vite build ──
if [[ "${SKIP_NPM:-0}" != "1" ]]; then
    if ! command -v npm >/dev/null 2>&1; then
        echo "HATA: npm bulunamadı. Önce: bash deploy/guzel-hosting/install-node.sh"
        exit 1
    fi

    echo "==> npm install + build..."
    if [[ -f package-lock.json ]]; then
        npm ci --ignore-scripts 2>/dev/null || npm install --ignore-scripts
    else
        npm install --ignore-scripts
    fi
    npm run build

    if [[ ! -f public/build/manifest.json ]]; then
        echo "HATA: public/build/manifest.json oluşmadı. npm run build başarısız."
        exit 1
    fi
else
    echo "==> npm atlandı (SKIP_NPM=1)"
fi

# ── Veritabanı ──
echo "==> migrate + seed (ilk admin/kasa/garson kullanıcıları)..."
php artisan migrate --force --seed

echo "==> storage:link..."
php artisan storage:link 2>/dev/null || true

# ── İzinler ──
echo "==> storage / bootstrap/cache izinleri..."
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
if id www >/dev/null 2>&1; then
    chown -R www:www storage bootstrap/cache 2>/dev/null || true
fi

# ── Cache ──
echo "==> config / route / view cache..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "=========================================="
echo " Kurulum tamamlandı."
echo "=========================================="
echo ""
echo "Sonraki adımlar:"
echo "  1. Supervisor: deploy/guzel-hosting/supervisor-*.conf (yolları düzenleyin)"
echo "     supervisorctl reread && supervisorctl update"
echo "     supervisorctl start human-reverb human-queue"
echo "  2. Cron: * * * * * cd $ROOT && php artisan schedule:run"
echo "  3. Nginx location /app (SSL sonrası) — deploy/guzel-hosting/nginx-websocket.conf"
echo "  4. Admin şifrelerini değiştirin: /admin/giris"
echo "  5. Test: curl -sI \$(grep ^APP_URL= .env | cut -d= -f2)/up"
echo ""
echo "Varsayılan giriş (seed): admin@human.com / human2026"
echo ""
