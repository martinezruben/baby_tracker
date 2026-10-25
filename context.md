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

1. ¿Uso personal o comercial?
2. ¿Inertia + React, o Livewire?
3. ¿Qué funciones extra se quieren además de las del original?

## Fuentes

- [Sprout Track en GitHub](https://github.com/Oak-and-Sprout/sprout-track)
- [Licencia](https://github.com/Oak-and-Sprout/sprout-track/blob/main/LICENSE.md)
