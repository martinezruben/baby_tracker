#!/usr/bin/env bash
# Prepara la app en el servidor tras subir el código (git pull o copia de archivos).
# Requiere PHP 8.3+, Composer y Node 20+. Uso: bash scripts/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "Falta .env: copia .env.production.example y complétalo."; exit 1; }

composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

# public/build NO está en git: sin este paso toda página da error 500 ("Vite manifest not found").
npm ci
npm run build

php artisan migrate --force
php artisan storage:link 2>/dev/null || true
# Cachea configuración, rutas, vistas y eventos: evita leer y analizar decenas de archivos en cada petición.
php artisan optimize

echo "Listo. Programa en cron: * * * * * php $(pwd)/artisan schedule:run >> /dev/null 2>&1"
