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

## Repositorio

- Rama de desarrollo: `claude/jolly-johnson-ya45cu`. `main` solo recibe cambios por Pull Request.
- No subas `.env` ni claves VAPID.
