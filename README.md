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
