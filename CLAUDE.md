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

## Autenticación

- No hay usuarios con correo: quien inicia sesión es un **cuidador** (`Caretaker`) con PIN de 4 a 10 dígitos, guardado cifrado. Cinco intentos fallidos bloquean 5 minutos (`App\Support\PinThrottle`).
- Primer arranque: `/configuracion` (asistente de 3 pasos: familia, PIN del administrador, bebé). Solo existe mientras no haya familia.
- Cambio rápido de cuidador: se vuelve a `/entrar` y se elige otro con su PIN.
- Textos de interfaz en `lang/{es,en}/ui.php` (es es el respaldo); el frontend los lee con `useT()`.

## Panel de Inicio y registro de actividades

- Fuente de verdad visual: `docs/mockup/*.dc.html`; los tokens (colores, sombras, Outfit) viven en `resources/css/app.css`. Barra flotante: `FloatingNav`; formularios como hoja inferior: `SheetLayout`; hora con atajos: `TimeBlock`.
- Registro: cada tipo es una clase en `app/Support/Activities/` registrada en `ActivityRegistry` (reglas, creación, resumen, repetir). Rutas `/registrar/{tipo}`; deshacer = borrado lógico vía `records.destroy` (el `Snackbar` lo ofrece 6 s).
- Pecho = cronómetro (`BreastFeedSession`, tabla `active_breast_feeds`): guarda segundos por lado y el instante de la última acción. Sueño en curso = `SleepLog` sin `ended_at`.
- Fechas: se guardan en UTC; lo que escribe o ve una persona va en hora de la familia (`FamilyClock` en PHP, `lib/time.js` en JS) y en 12/24 h según `family_settings.time_format`.
- Módulos aún sin construir aparecen atenuados con "Próximamente" (`available: false` en `resources/js/lib/activities.js`); al construirlos, cámbialos a `true`.
- `tests/Unit/TraduccionesTest` falla si un `t('clave')` del frontend no existe en `lang/es` o si `en` y `es` difieren.

## Notificaciones (Web Push)

- Canal `push` = `WebPushChannel` (librería `minishlink/web-push`); el cliente de envío es `PushClient` (se sustituye por un falso en las pruebas).
- Claves VAPID: `php artisan webpush:vapid` (imprime) o `--write` (las guarda en el `.env` si no existen). Nunca en el repo.
- El service worker es `public/sw.js`. Los botones del aviso (Confirmar / Posponer) llaman a enlaces **firmados y con caducidad** (`reminders.act`), sin sesión ni CSRF.
- El aviso solo lleva el título y el texto de la regla: nunca fotos ni ubicación.
- Pantalla `/recordatorios` (`ReminderController`, `SaveReminder`): recordatorios sueltos (tipo `custom`) con varios momentos de aviso, repetición, "insistir hasta confirmar", snooze, horario silencioso y destinatarios. La fecha se escribe en la zona horaria de la familia y se guarda en UTC. Otros módulos (citas, medicinas, vacunas) crearán sus reglas con `NotificationScheduler`.
- "Enviar aviso de prueba" (`/push/prueba`) comprueba que el dispositivo recibe avisos.
- iPhone: los avisos solo funcionan con la app instalada en la pantalla de inicio (falta el manifest PWA).

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
