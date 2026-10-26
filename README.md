# Kidu Baby Tracker

Aplicación personal para el seguimiento del bebé (sueño, alimentación, pañales, medicinas, vacunas, citas médicas, fotos, eventos y notificaciones configurables). Móvil primero, en español, con soporte para 5 idiomas.

**Stack:** Laravel 13, MySQL 8, Inertia + React, Tailwind CSS 4, Docker (Sail), Redis.

## Basado en Sprout Track

Este proyecto está basado en [Sprout Track](https://github.com/Oak-and-Sprout/sprout-track) de John Overton y Oak and Sprout. Se distribuye bajo los mismos términos (uso **no comercial**, con atribución y share-alike). Ver [`LICENSE.md`](LICENSE.md).

## Instalación (Docker / Sail)

```bash
cp .env.example .env
composer install            # o: docker run --rm -v "$(pwd)":/app -w /app composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

La app queda en <http://localhost>. Mailpit en <http://localhost:8025>.

## Instalación local (sin Docker)

Requiere PHP 8.3+, Composer, Node 22+ y MySQL 8.

```bash
cp .env.example .env
composer install && npm install
php artisan key:generate && php artisan migrate
npm run dev & php artisan serve
```

## Avisos push

```bash
php artisan webpush:vapid --write   # genera las claves VAPID y las guarda en .env (solo si no existen)
```

Los avisos necesitan HTTPS (o `localhost`). Cada cuidador activa los avisos en su dispositivo desde la pantalla de inicio. El programador envía los avisos pendientes cada minuto: en producción añade `* * * * * php artisan schedule:run` al cron (con Sail: `sail artisan schedule:work`).

## Pruebas y estilo

```bash
php artisan test
vendor/bin/pint --test
```

## Documentación

- [`context.md`](context.md): alcance, inventario de funciones y plan.
- [`docs/DISENO-UI.md`](docs/DISENO-UI.md): reglas de diseño de la interfaz.
- [`CLAUDE.md`](CLAUDE.md): instrucciones para Claude Code.

## Seguridad

No subas `.env`, claves VAPID ni datos reales del bebé. Cambia las credenciales por defecto antes de usar la app.

## Despliegue en un servidor (sin Docker)

1. Sube el código y apunta el dominio a la carpeta `public/`.
2. Copia `.env.production.example` a `.env` y completa la base de datos y `APP_URL`.
3. Ejecuta `bash scripts/deploy.sh` (instala dependencias, **compila el frontend**, migra y cachea).
4. Programa el cron cada minuto: `* * * * * php /ruta/artisan schedule:run` (envía los recordatorios).
5. Dale permisos de escritura al servidor web sobre `storage/` y `bootstrap/cache/`.

**Error 500 en todas las páginas:** casi siempre es `Vite manifest not found`. La carpeta `public/build` no se sube con git; hay que generarla con `npm ci && npm run build` en el servidor (o compilarla en tu equipo y subirla). Otras causas habituales: falta `.env` o `APP_KEY`, `vendor/` sin instalar (`composer install --no-dev`), `storage/` sin permisos de escritura, migraciones sin correr, o `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION` apuntando a Redis sin tenerlo (usa `database`). El detalle está en `storage/logs/laravel.log`.

**Error al crear un respaldo:** la pantalla muestra el motivo. Las causas habituales son que `storage/` no tenga permiso de escritura para el usuario del servidor web (`chown -R www-data storage bootstrap/cache`; en contenedores suele pertenecer a root), que a PHP le falte la extensión `zip`, o que se acabe el espacio en disco. El detalle también queda en `storage/logs/laravel.log`.

## Rendimiento

Lo que ya hace la app: las traducciones (~43 KB) se envían solo en la primera carga y el navegador las recuerda; los bebés y la familia se consultan una vez por petición; las pantallas más usadas se descargan en segundo plano cuando el navegador está libre; y el service worker guarda en el teléfono los archivos de `/build/assets/` (llevan el hash en el nombre, no cambian), así que las visitas siguientes no esperan a la red.

Lo que depende del servidor y conviene revisar (suele ser lo que más se nota):

1. **Sesiones y caché sin base de datos.** `SESSION_DRIVER=file` o `redis` y `CACHE_STORE=file` o `redis` (ver `.env.production.example`). Con `database`, cada toque hace consultas y una escritura extra solo para la sesión.
2. **`php artisan optimize`** después de cada despliegue (lo hace `scripts/deploy.sh`) y `APP_DEBUG=false`.
3. **OPcache activo** en PHP: `opcache.enable=1`, `opcache.memory_consumption=128`, `opcache.validate_timestamps=0` (reinicia PHP-FPM al desplegar). Sin él PHP recompila todo el framework en cada petición.
4. **Compresión y caché de archivos estáticos** en el servidor web. Ejemplo para nginx:
   ```nginx
   gzip on;
   gzip_types text/css application/javascript application/json image/svg+xml;
   location /build/assets/ { expires 1y; add_header Cache-Control "public, immutable"; access_log off; }
   location /icons/ { expires 30d; }
   ```
   (con Apache: `mod_deflate` y `mod_expires` para `/build/assets/`). Si usas Cloudflare u otro proxy, activa Brotli.
5. **Base de datos cerca de la app** (mismo servidor o misma región): cada pantalla hace de 5 a 25 consultas.
6. **HTTP/2 o HTTP/3** y HTTPS con certificado completo.
