# Deploy — GitHub → Sunucu

## Git'e gidenler

| Dosya | Açıklama |
|-------|----------|
| `composer.lock` | PHP 8.3 uyumlu |
| `package-lock.json` | npm sürümleri |
| `.env.production.example` | Canlı şablon (secret yok) |
| `deploy/` | Kurulum scriptleri |

## Git'e GİTMEyenler (.gitignore)

- `.env` — sunucuda elle oluşturulur
- `vendor/` — `composer install`
- `node_modules/` — `npm install`
- `public/build/` — `npm run build`
- `storage/` içeriği (log, upload)

## İlk kurulum (sunucu)

```bash
cd /www/wwwroot
git clone https://github.com/KULLANICI/human-qr-menu.git humansocialpeople.com
cd humansocialpeople.com

cp .env.production.example .env
nano .env   # DB_PASSWORD, APP_URL, REVERB_HOST

bash deploy/guzel-hosting/install-node.sh   # Node yoksa
bash deploy/install-production.sh
```

## Güncelleme

```bash
cd /www/wwwroot/humansocialpeople.com
bash deploy/update-production.sh
```

## aaPanel dosyaları

- `guzel-hosting/nginx-websocket.conf` — SSL sonrası Nginx
- `guzel-hosting/supervisor-*.conf` — Reverb + queue
- `guzel-hosting/TR-VPS-3-SWAP-VE-RAM.md` — RAM/swap
