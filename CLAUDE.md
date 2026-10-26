# Instrucciones para Claude Code

Proyecto: Kidu Baby Tracker (Laravel + MySQL). Interfaz móvil primero, en español.

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
- Editar: cada tipo implementa `attributes()` (lo comparten `create` y `update`) y `formValues()` (valores del formulario en hora de la familia). `GET /registros/{tipo}/{id}/editar` abre el mismo formulario con `record`; `PUT /registros/{tipo}/{id}` guarda y vuelve a la Bitácora. Los formularios usan `useActivityForm(tipo, valores, record)` y `SheetLayout editing`. La lactancia (`BreastActivity`) solo se edita (no está en el registro: se crea con el cronómetro).
- Tipos de registro: biberón, pañal, sueño, sólidos (`FoodLog` + `Food`), extracción (`PumpLog`), baño, juego, medicina, medida, nota y ánimo, más el pecho con cronómetro. Alta de un tipo: clase en `app/Support/Activities/`, registro en `ActivityRegistry`, página `Registrar/*.jsx`, `Timeline::sources()/data()`, `describe()` en `Bitacora.jsx`, textos es/en y `available: true`.
- Bitácora (`/bitacora`, `Timeline`): mezcla todos los tipos, paginada con cursor `instante|tipo|id` y `Inertia::merge`; eliminar desde ahí ofrece restaurar (`records.restore`). Al añadir un tipo de registro, añádelo también en `Timeline::sources()`.
- Módulos aún sin construir aparecen atenuados con "Próximamente" (`available: false` en `resources/js/lib/activities.js`); al construirlos, cámbialos a `true`.
- `tests/Unit/TraduccionesTest` falla si un `t('clave')` del frontend no existe en `lang/es` o si `en` y `es` difieren.

## Citas médicas, archivos y medicinas con tomas

- Cita (`Appointment`): motivo, doctor, lugar, fecha (UTC), estado (programada/realizada/cancelada), resultado y seguimiento. `SaveAppointment` crea/actualiza la cita, guarda lo medido como `Measurement` (con `appointment_id`) y mantiene **una** regla de recordatorio (avisos antes de la cita) que se apaga al cancelar, realizar o borrar.
- Archivos (`AppointmentFileController`): solo PDF e imágenes (validados por contenido), 10 MB y 10 por cita, en el disco **privado** `local`; se sirven únicamente por la ruta autenticada `appointments.files.show` (con `nosniff`). Nunca por `public/storage`.
- Cada archivo lleva **tipo** (receta, examen, informe, otro) y **descripción** opcionales: se eligen al subir (`AppointmentFiles.jsx`, arrays `categories[]`/`descriptions[]` alineados con `files[]`) y se corrigen con `PATCH citas/{cita}/archivos/{archivo}`. `/documentos` (`DocumentController`) busca en todas las citas del bebé por palabra (descripción, nombre del archivo, motivo o doctor; todas las palabras deben aparecer) y por tipo; el `LIKE` escapa con `!` para funcionar igual en MySQL y SQLite.
- Medicinas (`Prescription` + `PrescriptionDose`): `SavePrescription` genera todas las tomas (cada N horas) y una regla `Medicine` que insiste cada 10 min hasta confirmar y no respeta el horario silencioso. Confirmar el aviso dispara `ReminderAcknowledged` y `LogDoseWhenAcknowledged` marca la toma como dada y crea el `MedicineLog`. `RecordDose` (dar, omitir, deshacer) es la única vía para cambiar una toma.
- No se programan avisos para tomas que pasaron hace más de 10 minutos.

## Fotos y momentos

- Fotos (`Photo`, `PhotoStorage`): solo JPG, PNG y WebP (HEIC no: Safari lo convierte al elegirlo). La copia guardada se **vuelve a codificar**: se gira según su orientación, se reduce a 2048 px, se le quita todo el EXIF y se crea una miniatura de 480 px, en el disco privado `local`. La fecha y el GPS del EXIF pasan a `taken_at`, `latitude` y `longitude`, editables; "Quitar ubicación" los borra. Se sirven solo por `photos.file` / `photos.thumb` con sesión de la familia. Cuota por familia en `family_settings.photo_quota_mb` (2048 MB).
- Etiquetas (`Tag`): por familia, siempre en minúsculas (`Tag::normalize`). La galería filtra por etiqueta, favoritas, mes (en hora de la familia) y lugar, paginada por cursor `taken_at|id` con `Inertia::merge`.
- Momentos (`LifeEvent`, `/eventos`): diente, comida nueva, primeros pasos, gateo, palabra, viaje, enfermedad u otro; con fotos (`photos.life_event_id`). La fecha de la foto manda; si no trae EXIF, se usa el día del momento. Borrar un momento deja sus fotos en la galería.
- Las fotos y la ubicación nunca van en notificaciones.

## Vacunas

- El esquema (`VaccineDose`, por familia) es **dato**, no código: se carga desde la cartilla en `/vacunas/esquema` (una dosis, o pegando líneas "vacuna; dosis; edad; notas" con `VaccineScheduleImport`, todo o nada). No hay esquema precargado; no inventes edades ni dosis.
- `VaccineSchedule` calcula el estado de cada dosis para un bebé desde su fecha de nacimiento: `applied`, `overdue`, `soon` (≤ 60 días) o `later`. Una aplicación (`Vaccination`) puede estar o no ligada a una dosis del esquema; una dosis se aplica una sola vez por bebé.
- `SyncVaccineReminders` deja una regla por bebé y dosis pendiente (avisos 1 semana antes, 1 día antes y el día, a las 9:00 locales) y apaga la de las aplicadas, vencidas o borradas. Se llama al aplicar, deshacer, editar el esquema o importar; es idempotente.

## Calendario

- `/calendario` (`CalendarData`): un mes del bebé activo agrupado por día **de la familia**: citas (no canceladas), vacunas (por aplicar y aplicadas), tomas de medicina, recordatorios, momentos, y solo el **conteo** de tomas, sueños, pañales y medicinas registradas. Cada día enlaza al detalle de cada cosa y a `/bitacora?dia=YYYY-MM-DD`.
- Al añadir un módulo con fechas, súmalo en `CalendarData::month()` y en `ITEM_STYLES` / `DOTS` de `pages/Calendario.jsx`.

## Ajustes de la familia y PWA

- `/ajustes` (solo administradores, middleware `admin`): hub con cuidadores, bebés, preferencias y esquema de vacunación. Controladores `SettingsController`, `CaretakerAdminController`, `BabyAdminController`; el idioma personal se cambia en `/mas` (`PATCH /cuidador/idioma`). Idiomas implementados: `es` y `en`.
- `SaveCaretaker`: crea (hereda los avisos compartidos de citas, medicinas y vacunas), edita (no deja sin administrador), da de baja (no a uno mismo ni al último admin; borra suscripciones push y sesiones, lo quita de los avisos) y reactiva. Dar de baja un bebé = borrado lógico y apaga sus reglas; nunca el último.
- Al cambiar la zona horaria se vuelven a sincronizar los avisos de vacunas.
- PWA: `public/manifest.webmanifest` (standalone, `#8B6340`), iconos en `public/icons/` generados con `php scripts/make-icons.php`, metaetiquetas de iPhone en `app.blade.php` y `InstallHint` en `/mas`. El manifest es estático (no por familia).

## Marca

- El nombre de la app es **Kidu Baby Tracker** (`app.name` en `lang/*/ui.php`, `APP_NAME`, manifest; nombre corto «Kidu»). Úsalo siempre así en pantallas, avisos, correos y documentos.
- Logo (carrito con reloj, tonos beige): original en `resources/brand/kidu-logo.png`; `php scripts/make-icons.php` genera `public/brand/kidu-mark.png` (fondo transparente) y los iconos de la PWA. La interfaz mantiene los tokens de color de `app.css`.

## Contactos y alergias

- Contactos (`Contact`, `/contactos`, `ContactController`): directorio **de la familia** (nombre, quién es, teléfono, correo, dirección, notas). Teléfono con `tel:`, correo con `mailto:`, dirección con enlace a mapas (solo se abre al tocar). Borrar = borrado lógico con deshacer (`contacts.restore`).
- Alergias (`BabyAllergen`, `/alergias`, `AllergyController`): por **bebé activo**, tipo alimento / medicina / ambiente / otra, reacción y notas. `RegistrarController` envía a los formularios de sólidos (alergias de alimento) y medicina (de medicina) la lista de nombres; `AllergyWarning` + `allergyHit()` avisan sin bloquear si el nombre coincide (sin acentos ni mayúsculas, por inclusión). Es una ayuda: no sustituye al pediatra.

## Temas: claro, oscuro y nursery

- Los componentes solo usan tokens de `resources/css/app.css`; `:root[data-theme='dark'|'nursery']` los redefinen (nunca colores sueltos: usa `text-on-primary`, `text-on-ink`, `text-danger-ink`, `bg-scrim`…, no `text-white` ni `text-red-*`). Oscuro = marrón grisáceo cálido, no negro puro; nursery = fondo casi negro y texto ámbar tenue, sin colores fríos.
- Preferencia por **dispositivo** (`localStorage`: `kidu-theme` auto|light|dark|nursery, `kidu-brightness`, `kidu-theme-before`). `lib/theme.js` (`useTheme`, `setTheme`, `enterNursery`/`exitNursery`) y `components/ThemeEffects` (velo de brillo y Wake Lock en nursery); el script de `app.blade.php` aplica el tema antes de pintar. "Auto" sigue `prefers-color-scheme`.
- `/nursery` (`NurseryController`, `Nursery.jsx`): reloj grande, última toma y pañal, Pañal / Biberón / Pecho / Dormir (o Despertó) y brillo. Los formularios abiertos con `?nursery=1` vuelven a `/nursery` al guardar o cerrar.
- `tests/Unit/TemaContrasteTest` exige contraste AA en los tres temas: si cambias un token, vuelve a correrlo.

## Crecimiento y reportes

- `/crecimiento` (`GrowthController`, `Support/Growth/GrowthReport`): peso, talla y perímetro cefálico sobre curvas de percentiles 3/15/50/85/97 con el percentil de cada medida. Las tablas LMS oficiales de la OMS (0-5 años) y del CDC (0-36 meses) están en `resources/data/growth.json` (son datos; no los edites a mano ni inventes valores). `GrowthStandards` calcula z y percentil (`z = ((x/M)^L − 1)/(L·S)`); si el estándar elegido es CDC y el bebé pasa de 36 meses, se usa la OMS. Hace falta el sexo del bebé (Ajustes > Bebés); las medidas se convierten a kg/cm para el cálculo y se muestran en las unidades de la familia.
- `/reportes` (`ReportController`, `ReportData`): promedios y barras por día (tomas, sueño, pañales) de 7, 14 o 30 días en hora de la familia, tabla alterna, `/reportes/exportar` (CSV con BOM, protegido contra fórmulas de Excel) e impresión/PDF con `window.print()`.
- Gráficos en SVG propio (sin librería): una sola serie por gráfico, barras con 2 px de separación y extremo redondeado, tabla alterna.

## Notificaciones (Web Push)

- Canal `push` = `WebPushChannel` (librería `minishlink/web-push`); el cliente de envío es `PushClient` (se sustituye por un falso en las pruebas).
- Claves VAPID: `php artisan webpush:vapid` (imprime) o `--write` (las guarda en el `.env` si no existen). Nunca en el repo.
- El service worker es `public/sw.js`. Los botones del aviso (Confirmar / Posponer) llaman a enlaces **firmados y con caducidad** (`reminders.act`), sin sesión ni CSRF.
- El aviso solo lleva el título y el texto de la regla: nunca fotos ni ubicación.
- Pantalla `/recordatorios` (`ReminderController`, `SaveReminder`): recordatorios sueltos (tipo `custom`) con varios momentos de aviso, repetición, "insistir hasta confirmar", snooze, horario silencioso y destinatarios. La fecha se escribe en la zona horaria de la familia y se guarda en UTC. Otros módulos (citas, medicinas, vacunas) crearán sus reglas con `NotificationScheduler`.
- "Enviar aviso de prueba" (`/push/prueba`) comprueba que el dispositivo recibe avisos.
- iPhone: los avisos solo funcionan con la app instalada en la pantalla de inicio (el manifest PWA ya existe).

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
