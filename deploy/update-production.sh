#!/usr/bin/env bash
# Güncelleme: git pull sonrası
# Kullanım: bash deploy/update-production.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> git pull..."
git pull --ff-only

bash deploy/guzel-hosting/deploy.sh

echo ""
echo "Supervisor yenileyin (Reverb/queue kodu değiştiyse):"
echo "  supervisorctl restart human-reverb human-queue"
