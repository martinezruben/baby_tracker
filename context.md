# Baby Tracker (Laravel + MySQL) — Contexto y plan

## Licencia del original

La licencia de Sprout Track permite copiar y modificar, pero **solo para uso no comercial**. También exige atribución a los autores (John Overton y Oak and Sprout) y que tu versión use la misma licencia. Si la idea es vender el proyecto o cobrar por usarlo, no se puede hacer con su código ni con un port directo. En ese caso habría que escribirlo desde cero con diseño propio.

## Qué es el original

Una app Next.js (App Router) con Prisma sobre SQLite o PostgreSQL, Tailwind y Docker.

**Qué incluye:**
- Seguimiento de sueño, alimentación, pañales, baño, medidas, medicinas, hitos y contactos.
- Calendario, reportes con curvas de crecimiento y PDF, y exportación a CSV/XLSX.
- Modo nursery, modo oscuro y 5 idiomas.
- PWA con notificaciones push.
- API keys y webhooks.
- Varias familias y varios cuidadores con PIN.
- Panel de administración (`/family-manager`) y respaldos.

## Inventario completo de Sprout Track (referencia para el port)

### Funciones
- **Seguimiento y dashboard diario:** sueño, alimentación, pañales, baño, medidas, medicinas, actividades e hitos; resumen del día con estadísticas en tiempo real y sesiones activas.
- **Calendario:** vista mensual con indicadores de color por tipo de actividad y detalle al tocar un día.
- **Reportes:** reportes mensuales con métricas y curvas de percentiles de crecimiento, estadísticas de alimentación, análisis de sueño, desglose de actividades y boleta mensual en PDF.
- **Bitácora y exportación:** log completo con búsqueda, filtros y paginación; exportación a CSV y XLSX.
- **Modo nursery:** pantalla completa, botones grandes, color de fondo y brillo configurables.
- **Modo oscuro** en toda la app.
- **5 idiomas:** inglés, español, francés, alemán e italiano; preferencia guardada por usuario.
- **Notificaciones push:** alertas a cuidadores cuando se registra una actividad, y avisos por temporizador vencido (alimentación, pañal, medicina).
- **API y webhooks:** gestión de API keys (con scopes) y soporte de webhooks.
- **PWA:** instalable, con notificaciones, modo keep-awake y pantalla completa.
- **Multi-familia:** dashboards separados y privados por familia.
- **Multi-cuidador:** PIN individual para padres, abuelos, niñeras y guardería (o un PIN global).
- **Cuentas familiares:** varios bebés en un mismo espacio familiar.
- **Contactos compartidos:** vinculables a medicinas, vacunas y eventos del calendario.
- **Respaldo y restauración** de la base de datos integrados.
- **Fotos:** galería por bebé con miniaturas, favoritos, vínculos a actividades e hitos, y cuota por familia.
- **Vacunas:** registro de dosis con documentos adjuntos.
- **Alergias y alimentos sólidos:** catálogo de alimentos, disfrute, reacciones y alérgenos del bebé.
- **Extracción de leche (pump)** y ajustes de inventario de leche materna.
- **Estados de ánimo, notas y juego** (tummy time, paseo, etc.).
- **Sesiones activas:** lactancia con cronómetro por lado y pausa; actividades de juego con cronómetro.
- **Calendario avanzado:** eventos con recurrencia, recordatorios, citas y horarios de cuidadores.
- **Importación externa** con trazabilidad por proveedor (`ExternalImportRecord`).
- **Feedback** con adjuntos y respuestas encadenadas.
- **Configuración de email** (SMTP, SendGrid, SMTP2GO) y de dominio/HTTPS.

### Funciones adicionales detectadas en la revisión del código (rutas API y componentes)
- **Importadores externos:** asistente de importación en 2 pasos (configurar y revisar) desde **Baby Buddy** (CSV de niño, actividades y medicación) y **Huckleberry** (CSV de alimentación, pañales y extracción), con deduplicación por registro de origen.
- **Balance de leche materna:** endpoint y vista de inventario (extraída menos consumida, más ajustes manuales).
- **Línea de tiempo (timeline):** vista cronológica unificada de todas las actividades, además de la bitácora completa.
- **Últimas actividades y próximos eventos por bebé:** tarjetas de acceso rápido (`BabyQuickInfo`, `BabyQuickStats`, `DailyStats`).
- **Selector de bebé** y **banners de sesión activa** (lactancia y actividad en curso).
- **Ajustes configurables por familia:**
  - visibilidad de cada tipo de actividad en el dashboard (`activity-settings`);
  - ubicaciones de sueño personalizadas (`sleep-locations`);
  - tipos de baño personalizados (`bath-type-settings`);
  - ajustes del modo nursery;
  - tipos de alimentación que reinician el temporizador (`feedTimerTypes`);
  - unidades por defecto y estándar de curvas de crecimiento (CDC u OMS);
  - zona horaria del sistema.
- **Gestión de cuidadores:** alta, edición, cambio de PIN propio (`ChangePinModal`), colores de insignia y desactivación.
- **Invitaciones a familias** (`ActiveInviteView`, `FamilySetup` con token y expiración) y gestión de cuentas desde el Family Manager, con vistas móviles.
- **PWA por familia:** `manifest` dinámico por slug.
- **Localización:** endpoint de traducciones y selector de idioma por usuario.
- **Changelog, política de privacidad y términos de uso** como modales dentro de la app.
- **Actualización del guardián (`guardian/update`)** y configuración del despliegue (`deployment-config`, `app-config`).
- **Detalle de foto, galería y formulario de foto** (`PhotoGallery`, `PhotoDetail`, `PhotoForm`).
- **Formularios existentes:** actividad, bebé, baño, evento de calendario, cuidador, contacto, pañal, familia, alimentación, alimento, dar medicina, medida, medicina, hito, nota, foto, extracción, ajustes, sueño, vacuna y feedback.
- **Tarjeta visual del reporte mensual (`reporting/CardVisual`)** que se exporta como PDF.
- **Hooks de integración versionados (`hooks/v1`)**: la API de webhooks usa API keys con scopes y está versionada.
- **Documentación:** manual de usuario (a mano), documentación de administración y de arquitectura, y CSV de percentiles CDC/OMS.
- **Solo del servicio hospedado (no se portan):** `landing`, `landing-stats`, `analytics`, `beta-signup`, `beta-subscribers`, `gift-codes`, `short-links`, `ExpiredAccountMessage` y suscripciones/facturación.

### Administración e instalación
- **Asistente inicial (3 pasos):** (1) familia: nombre, slug de URL e importación opcional; (2) seguridad: PIN global o PIN por cuidador; (3) bebé: nombre, fecha de nacimiento y umbrales de alerta de alimentación y pañal.
- **Family Manager (`/family-manager`):** dominio, HTTPS, email, respaldos de BD y notificaciones push, protegido con contraseña de administrador.
- **Credenciales por defecto del original (PIN `111222`, contraseña `admin`):** en nuestra versión deben generarse aleatorias y obligar a cambiarlas.

### Integraciones
- Home Assistant, Grafana, etiquetas NFC, API de webhooks genérica y Web Push (claves VAPID, cron, configuración por usuario).

### Stack y despliegue del original
- Next.js (App Router), Prisma (SQLite o PostgreSQL 14+ con base de datos separada para logs), Tailwind, Docker.
- Opciones: Docker con SQLite, Docker con PostgreSQL, Docker Compose, instalación local (Node 22+) con o sin PostgreSQL y servicio systemd, y demo pública que se reinicia cada hora.

### Seguridad del original
- PIN por cuidador o global, contraseña del Family Manager, API keys hasheadas con scopes y expiración, logging opcional de requests/responses de la API, autogeneración de variables de entorno y reseteo automático de la contraseña admin al actualizar desde versiones antiguas.

### Modelo de datos (68 modelos, 19 enums)
- **Cuenta y familia:** Account, Family, FamilyMember, Caretaker (rol USER/ADMIN, PIN, color de insignia), Baby, Settings, FamilySetup.
- **Registros de actividad** (comparten `id`, timestamps, `deletedAt` para borrado lógico, `familyId`, `babyId`, `caretakerId`): SleepLog, FeedLog, DiaperLog, MoodLog, Note, Milestone, PumpLog, BreastMilkAdjustment, PlayLog, BathLog, Measurement, VaccineLog, VaccineDocument, MedicineLog, FoodLog, PhotoLog.
- **Catálogos:** Medicine, Food, BabyAllergen, Unit, Contact (con tablas puente ContactMedicine y ContactVaccine).
- **Calendario:** CalendarEvent con tablas puente BabyEvent, CaretakerEvent y ContactEvent.
- **Fotos:** Photo, PhotoLink (polimórfico por tipo de actividad e id), PhotoFavorite.
- **Notificaciones:** PushSubscription, DeviceToken, NotificationPreference, NotificationLog.
- **Sesiones activas:** ActiveBreastFeed y ActiveActivity (una por bebé).
- **API e importación:** ApiKey, ExternalImportRecord.
- **Soporte:** Feedback y FeedbackAttachment.
- **Configuración del sistema:** AppConfig, EmailConfig, NotificationConfig.
- **Referencia de crecimiento (tablas LMS con percentiles p3–p97):** CDC (peso, longitud, perímetro cefálico para 0–36 meses; peso y estatura para 24–240 meses) y OMS/WHO (peso, longitud, perímetro cefálico para 0–24 meses).
- **Enums principales:** SleepType, SleepQuality, FeedType (BREAST/BOTTLE/SOLIDS), BreastSide, DiaperType, Mood, MilestoneCategory, PlayType, MeasurementType, CalendarEventType, RecurrencePattern, NotificationEventType, AllergenType, FoodEnjoyment, Gender, UserRole.
- **Solo del servicio hospedado, no se portan:** cuentas con facturación, GiftCode (Stripe), Beta* (campañas de email), ShortLink/ShortLinkClick, Pageview y DemoTracker.

### Notas para el port a Laravel
- Prisma → migraciones y modelos Eloquent con `SoftDeletes`; las 3 tablas de percentiles se cargan con seeders desde los datos CDC y OMS.
- Los enums pasan a enums de PHP 8.1 con casts en los modelos.
- Web Push con `laravel-notification-channels/webpush`; cron de temporizadores con el scheduler de Laravel.
- Respaldos con `spatie/laravel-backup`; sustituye también el respaldo propio del Family Manager.

## Plan para la versión en Laravel + MySQL

**Fase 1: Base (semana 1)**
1. Crear el proyecto Laravel con MySQL 8, Docker (Sail) y Git.
2. Frontend: Inertia + React + Tailwind (más fácil de portar desde el original), o Livewire si se prefiere solo PHP.
3. Pasar el `schema.prisma` a migraciones y modelos Eloquent.

**Fase 2: Núcleo (semanas 2-3)**
4. Autenticación por PIN con familias y cuidadores (Sanctum), más el asistente de configuración inicial.
5. CRUD de bebés y de todas las actividades, con el dashboard diario.
6. Calendario y bitácora con filtros y paginación.

**Fase 3: Funciones avanzadas (semanas 4-5)**
7. Reportes y curvas de percentiles (Chart.js), PDF (DomPDF) y exportación Excel (Laravel Excel).
8. i18n en 5 idiomas, modo oscuro y modo nursery.
9. PWA con Web Push, API keys y webhooks.
10. Panel de administración y respaldos (spatie/laravel-backup).

**Fase 4: "Y más" (después)**
- Roles y permisos más finos, recordatorios programados con el scheduler de Laravel, colas con Redis y app móvil.
- Pruebas con Pest y CI en GitHub Actions.

## Plan para empezar con GitHub desde el día 1

**1. Crear el repo (manual, 2 minutos)**
- GitHub → New repository, nombre sugerido: `baby-tracker-laravel`.
- **Privado** (son datos del bebé).
- Marcar "Add .gitignore" → *Laravel* y no agregar licencia todavía.

**2. Licencia y atribución (obligatorio por el original)**
- Agregar un `LICENSE.md` con los mismos términos de Sprout Track: no comercial, con atribución y share-alike.
- Agregar una sección "Basado en Sprout Track" en el README, con enlace al original y los autores.

**3. Conectar Claude al repo**
- Con el repo creado, pasar `usuario/repo` para adjuntarlo a la sesión, clonarlo y trabajar ahí.
- Para poder hacer commits y push, el repo debe estar autorizado para la cuenta en la conexión de GitHub de Claude (Ajustes → Conectores).

**4. Estructura de ramas**
- `main`: siempre estable.
- `develop`: integración.
- Una rama por función (`feature/citas-medicas`, `feature/vacunas`, `feature/push`) y un Pull Request por cada una.

**5. Primer commit (Fase 1)**
- Proyecto Laravel + Docker (Sail) con MySQL.
- `.env.example` sin secretos. El `.env` real y las claves VAPID nunca se suben.
- README con instrucciones de instalación, y `LICENSE.md`.

**6. Automatización básica**
- GitHub Actions: en cada PR corre las pruebas (Pest) y el estilo de código (Pint).
- Protección de `main`: solo se entra por PR.

**7. Organización del trabajo**
- Issues y un Project (tablero) con las fases: Base, Registro diario, Módulo médico, Notificaciones y Cierre.
- Un issue por módulo: citas, vacunas, medicinas, eventos y fotos.

## Preguntas pendientes

**Decidido:**
- Uso **personal** (no comercial): se puede portar el código con la licencia y atribución de Sprout Track.
- Frontend: **Inertia + React + Tailwind**.
- Alcance: **todas** las funciones del original (menos las del servicio hospedado) **más un sistema de notificaciones configurable** (ver abajo).

## Notificaciones configurables (función extra)

El original solo avisa al registrar una actividad y cuando vence un temporizador (alimentación, pañal, medicina), con un intervalo fijo por evento. Aquí debe poder configurarse todo.

**Requisitos**
- Reglas de notificación por usuario, bebé y tipo de evento (alimentación, pañal, sueño, medicina, vacuna, cita, recordatorio personalizado).
- **Frecuencia configurable:** una vez, o repetida cada N minutos/horas, con límite de repeticiones o ventana horaria (por ejemplo, no avisar de noche).
- **Más de un aviso por evento:** por ejemplo 1 día antes, 1 hora antes y al momento.
- **Snooze:** posponer 5, 10, 15 o 30 minutos o un tiempo personalizado.
- **Insistente hasta confirmar:** algunas reglas se repiten hasta que el usuario las acepta (confirmar o marcar como hecho).
- **Confirmación compartida:** si una persona confirma, se detienen los avisos para los demás cuidadores de esa regla (configurable).
- Canales: Web Push (PWA) como base; correo como respaldo; el resto queda para después.
- Historial de notificaciones enviadas, confirmadas y pospuestas, con reintentos y limpieza de suscripciones caducadas.

**Diseño propuesto (Laravel)**
- Tablas: `notification_rules` (tipo de evento, bebé, destinatarios, canal, `repeat_every_minutes`, `max_repeats`, `quiet_hours`, `requires_ack`, `snooze_options`), `notification_schedules` (próximo envío por regla y evento) y `notification_deliveries` (estado: pendiente, enviada, vista, pospuesta, confirmada, fallida).
- El scheduler de Laravel (cada minuto) encola jobs en Redis para los envíos que vencen; cada envío reprograma el siguiente si la regla repite y no hay confirmación.
- Acciones sobre la notificación (Confirmar, Posponer) desde el propio push (acciones del service worker) y desde la app.
- Web Push con `laravel-notification-channels/webpush` y claves VAPID en `.env` (nunca en el repo).
- Pruebas con Pest para: repetición, snooze, confirmación compartida y horario silencioso.

## Preguntas pendientes

1. ¿Qué otras funciones extra quieres además de las notificaciones (por ejemplo citas médicas, fotos, eventos)?
2. ¿Los avisos insistentes deben llegar a todos los cuidadores o solo a quien los configuró?

## Fuentes

- [Sprout Track en GitHub](https://github.com/Oak-and-Sprout/sprout-track)
- [Licencia](https://github.com/Oak-and-Sprout/sprout-track/blob/main/LICENSE.md)
