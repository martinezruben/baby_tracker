# Instrucciones para Claude Code

Proyecto: Baby Tracker (Laravel + MySQL). Interfaz móvil primero, en español.

## Antes de tocar la interfaz

1. Lee `docs/DISENO-UI.md`: reglas de diseño, breakpoints, navegación, tokens, formularios, estados, accesibilidad, idiomas y PWA. Incluye la dirección visual del modo claro (sección 15).
2. Revisa `docs/mockup/`: código fuente del lienzo de diseño (`canvas.json` y los archivos `.dc.html`):
   - `Main.dc.html`: dashboard general del bebé, con acceso a todo.
   - `Registro.dc.html`: centro de registro de actividades.
   - `Panal.dc.html`: formulario de pañal.
   - `Actividad.dc.html`: resumen de actividad y crecimiento.
   - `Oscuro.dc.html` y `Nursery.dc.html`: modo oscuro y modo nursery. El modo oscuro aún usa la distribución anterior; aplica las reglas de la guía.

## Reglas al implementar

- Móvil primero (360–390 px); escritorio es una expansión, no un rediseño.
- Registro de cualquier actividad en menos de 3 toques desde el dashboard.
- Objetivos táctiles de al menos 48 px; texto de cuerpo de 16 px mínimo.
- Textos en archivos de traducción (5 idiomas), nunca fijos en componentes.
- Contraste WCAG AA, también en modo oscuro.
- Usa los colores semánticos por tipo de actividad de la guía en calendario, bitácora y reportes.

## Stack y estructura

- Laravel 13 (PHP 8.3+), MySQL 8, Redis, Inertia + React (JSX) y Tailwind CSS 4; Docker con Sail.
- Páginas de Inertia en `resources/js/pages/` (minúscula, en español: `Inicio.jsx`); componentes en `resources/js/components/`.
- Tokens de color y tipografía (Outfit) en `resources/css/app.css` (`@theme`); no uses colores sueltos.
- Rutas en `routes/web.php`; lógica en controladores delgados + servicios/acciones en `app/`.
- Iconos: Lucide (`lucide-react`).

## Comandos

- Pruebas: `php artisan test` (usan SQLite en memoria). Estilo: `vendor/bin/pint` (CI corre `--test`).
- Frontend: `npm run dev` / `npm run build`.
- Con Docker: prefija con `./vendor/bin/sail`.

## Contexto y alcance

- `context.md`: alcance, inventario de funciones de Sprout Track, módulos extra (citas médicas, vacunas RD, fotos con etiquetas, eventos) y diseño de notificaciones (frecuencia, snooze, insistencia hasta confirmar, se detienen si un cuidador acepta).
- El esquema de vacunación de República Dominicana es **dato editable** (seeder), no código; las edades y dosis se cargan desde la cartilla del bebé. No inventes calendarios.
- Proyecto de uso **personal y no comercial**, basado en Sprout Track: mantén `LICENSE.md` y la atribución.

## Reglas de código

- Cada función nueva lleva su prueba (Pest/PHPUnit) y pasa Pint.
- Migraciones con `SoftDeletes` en registros de actividad; enums de PHP con casts.
- Archivos subidos (adjuntos, fotos) en disco privado; nunca mostrar fotos ni ubicación en notificaciones.
- Textos de interfaz en archivos de traducción (es, en, fr, de, it), nunca fijos.

## Repositorio

- Rama de desarrollo: `claude/jolly-johnson-ya45cu`. `main` solo recibe cambios por Pull Request.
- No subas `.env` ni claves VAPID.
- Ramas por función (`feature/...`) y Pull Request por cada una; `main` y `develop` protegidas.
