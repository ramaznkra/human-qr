#!/usr/bin/env bash
# aaPanel / Ubuntu 22.04 — Node.js 20 LTS (Vite build için)
# Kullanım: bash deploy/guzel-hosting/install-node.sh

set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
    echo "Root ile çalıştırın: sudo bash deploy/guzel-hosting/install-node.sh"
    exit 1
fi

echo "==> NodeSource 20.x repo ekleniyor..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -

echo "==> Node.js kuruluyor..."
apt-get install -y nodejs

echo "==> Sürümler:"
node -v
npm -v

echo ""
echo "Tamam. Proje klasöründe:"
echo "  cd /www/wwwroot/humansocialpeople.com"
echo "  npm ci --ignore-scripts || npm install --ignore-scripts"
echo "  npm run build"
echo ""
echo "Veya: bash deploy/guzel-hosting/deploy.sh"
