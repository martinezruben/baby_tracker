# Esquema y reglas de diseño de la interfaz (mobile-first)

Premisa: se usa sobre todo a una mano, de madrugada, con el bebé en brazos. Cada decisión debe servir para **registrar algo en menos de 3 toques**.

## 1. Principios

1. **Móvil primero.** Se diseña a 360-390 px y luego se expande a tablet y escritorio.
2. **Una mano, pulgar.** Las acciones principales van en la mitad inferior de la pantalla.
3. **Registro rápido.** Lo más frecuente (pañal, biberón, lactancia, sueño) nunca está a más de un toque del dashboard.
4. **Legible en la oscuridad.** El modo oscuro y el modo nursery son ciudadanos de primera, no un extra.
5. **Tolerante a errores.** Deshacer en lugar de confirmar. Los datos se guardan sin perder lo escrito.
6. **Calma visual.** Pocos colores, mucho espacio, sin saturar de datos.

## 2. Breakpoints (Tailwind)

| Rango | Layout |
|---|---|
| < 640 px (base) | Una columna, barra inferior, hojas inferiores (bottom sheets) |
| 640-1023 px (`sm`/`md`) | Una o dos columnas, barra inferior se mantiene |
| >= 1024 px (`lg`) | Barra lateral fija, contenido en 2-3 columnas, formularios en diálogo centrado |

Contenido con ancho máximo de ~1200 px en escritorio. Nada de scroll horizontal, salvo gráficos y tablas dentro de su contenedor.

## 3. Esquema de navegación

**Móvil: barra inferior con 5 destinos** (alcance del pulgar):

```
[ Inicio ] [ Calendario ] [  +  ] [ Bitácora ] [ Más ]
                       (botón central)
```

- **Inicio:** dashboard del día.
- **Calendario:** citas, vacunas, eventos.
- **+ (central, destacado):** abre la hoja de registro rápido.
- **Bitácora:** línea de tiempo y búsqueda.
- **Más:** reportes, fotos, medicinas, vacunas, citas, contactos, ajustes.

**Cabecera superior (fija, delgada):** selector de bebé (avatar + nombre), indicador de sesión activa y campana de notificaciones.

**Escritorio:** la misma estructura pasa a barra lateral izquierda; el + se convierte en botón "Registrar" arriba.

## 4. Esquema de pantallas clave

**Dashboard (Inicio)**
```
┌────────────────────────┐
│ ▾ Sofía      🔔  ⚙     │  cabecera
├────────────────────────┤
│ ● Lactando 12:04  [⏸][■]│  banner de sesión activa
├────────────────────────┤
│ Última toma   hace 2h10 │  tarjetas "hace cuánto"
│ Último pañal  hace 45m  │
│ Sueño         dormida ▸ │
├────────────────────────┤
│ Hoy: 6 tomas · 5 pañales│  estadísticas del día
│ · 11h sueño             │
├────────────────────────┤
│ Próximo: Vacuna 3 nov   │  próximos eventos
├────────────────────────┤
│ Línea de tiempo de hoy  │
└────────────────────────┘
 [Inicio][Cal][ + ][Bit][Más]
```

**Hoja de registro rápido (al tocar +):** cuadrícula de 2 x 4 con iconos grandes: Pañal, Biberón, Pecho, Sólidos, Sueño, Medicina, Extracción, Más. Un toque abre el formulario correspondiente ya con hora actual y los últimos valores usados.

**Formularios:** se abren como **hoja inferior a pantalla casi completa** en móvil y como diálogo centrado en escritorio.

## 5. Tokens de diseño

**Colores** (variables CSS, para cambiar de tema sin tocar componentes)
- Neutros con tono cálido; un color primario suave (ej. verde salvia o azul pizarra) y un color de acento para el +.
- Un color semántico por tipo de actividad (sueño, comida, pañal, medicina, vacuna, cita), usado siempre igual en calendario, bitácora y reportes.
- Semánticos: éxito, aviso, error, información.
- Contraste mínimo **4.5:1** en texto (WCAG AA), incluido el modo oscuro.
- Modo oscuro: gris azulado oscuro, **no negro puro** (evita halo en OLED); modo nursery: fondo casi negro, texto ámbar tenue, brillo configurable.

**Tipografía**
- Una sola familia sans-serif del sistema o Inter.
- Cuerpo **16 px mínimo** (evita el zoom automático de iOS al enfocar un campo).
- Escala: 12 / 14 / 16 / 20 / 24 / 32. Números de temporizadores y métricas en cifras tabulares.
- Respetar el tamaño de texto del sistema (usar `rem`, no `px` fijos).

**Espaciado y forma**
- Escala de 4 px (4, 8, 12, 16, 24, 32).
- Gutter lateral de 16 px en móvil.
- Radios: 12 px tarjetas, 16 px hojas, completo en botones de acción.
- Sombras mínimas; separar con espacio y bordes finos.

**Iconografía:** un solo set (Lucide), trazo uniforme, siempre con etiqueta de texto en acciones principales.

## 6. Reglas de interacción táctil

- **Objetivo táctil mínimo 48 x 48 px**, con 8 px de separación entre objetivos.
- Acciones frecuentes en la **zona del pulgar** (tercio inferior); acciones destructivas lejos de ella.
- **Botón principal por pantalla**, ancho completo, fijo en la parte inferior de los formularios.
- Gestos como atajo, nunca como única vía: deslizar para editar o eliminar, tirar para refrescar, pero siempre con botón visible equivalente.
- **Deshacer** (snackbar de 5-6 s) en vez de diálogos de "¿estás seguro?". La confirmación solo para borrados masivos o irreversibles.
- Retroalimentación inmediata: estado de pulsado, vibración suave opcional, actualización optimista de la lista.
- Evitar hover como único medio de mostrar algo.

## 7. Reglas de formularios

- **Valores por defecto inteligentes:** hora = ahora, bebé = el seleccionado, último tipo/cantidad usado, cuidador = el actual.
- Selectores como **chips o botones segmentados** (pañal: mojado / sucio / ambos), no listas desplegables, cuando haya 5 opciones o menos.
- Cantidades con **botones +/-** y teclado numérico (`inputmode="decimal"`).
- Fecha/hora con selector nativo; atajos "ahora" y "hace 15 min".
- Un campo por fila, etiqueta arriba, errores en línea y en lenguaje claro.
- Campos opcionales colapsados bajo "Más detalles"; el formulario básico cabe sin scroll.
- El teclado no debe tapar el botón de guardar (barra de acción sobre el teclado).
- Guardar borrador local si se pierde la conexión o se cierra la hoja.

## 8. Temporizadores y sesiones activas

- Banner persistente bajo la cabecera, visible en todas las pantallas.
- Lactancia: un cronómetro por lado, botones grandes de pausa/cambio/finalizar.
- Si se vence un temporizador (medicina, toma), la notificación lleva acciones directas ("Dada", "Posponer").
- El estado vive en el servidor y se sincroniza entre dispositivos y cuidadores.

## 9. Componentes base (biblioteca mínima)

Botón (primario/secundario/fantasma/peligro), campo, chip, interruptor, hoja inferior, diálogo, tarjeta, tarjeta "hace cuánto", ítem de línea de tiempo, selector de bebé, banner de sesión, snackbar, estado vacío, esqueleto de carga, gráfico (Chart.js con tema propio), tabla responsiva (se convierte en lista de tarjetas en móvil).

## 10. Estados que siempre se diseñan

Cargando (esqueletos, no spinners a pantalla completa), vacío (con una acción sugerida), error (con reintentar), sin conexión (aviso discreto y cola de envíos), éxito (snackbar breve).

## 11. Accesibilidad

- WCAG 2.1 AA: contraste, foco visible, navegación por teclado en escritorio.
- Nunca depender solo del color: icono o texto además del color.
- `aria-label` en botones de solo icono; anuncios `aria-live` para temporizadores y guardados.
- Respetar `prefers-reduced-motion` y `prefers-color-scheme`.
- Zoom del navegador permitido (no bloquear `viewport`).

## 12. Idiomas (5)

- Todo texto sale de archivos de traducción; ningún texto fijo en componentes.
- Diseñar con **+35 % de ancho** (el alemán y el francés son más largos): botones que crezcan en alto, no truncar etiquetas clave.
- Fechas, horas (12/24 h) y unidades (ml/oz, kg/lb, cm/in) según preferencia del usuario.

## 13. Rendimiento y PWA

- Objetivo en móvil de gama media con 4G: primera carga útil < 2.5 s (LCP), interacción < 200 ms.
- Carga diferida de reportes, gráficos y PDF; imágenes con miniaturas y `loading="lazy"`.
- PWA instalable: manifest por familia, iconos maskable, pantalla de inicio, `display: standalone`, `theme-color` según tema.
- Service worker: caché del shell de la app y cola de registros sin conexión que se sincroniza al volver.
- Respetar áreas seguras (`env(safe-area-inset-*)`) en la barra inferior (iPhone) y mantener la pantalla encendida solo en modo nursery.

## 14. Privacidad en la interfaz

- Las fotos y ubicaciones no se muestran en notificaciones ni en vistas previas.
- Opción de ocultar ubicación de una foto con un toque.
- Cambio de cuidador rápido con PIN, sin cerrar la sesión de la familia.

## 15. Dirección visual del modo claro (moderna)

- **Fondo** gris azulado muy claro `#F2F6F7`; superficies blancas con sombra suave; radios grandes (22-28 px en tarjetas, pastillas completas en botones y chips).
- **Tipografía:** Outfit (400/500/600/700), cifras tabulares.
- **Primario:** verde azulado `#087873` (texto blanco, contraste 5.3:1); acento brillante `#2CC5BD` solo con texto oscuro `#0B2A3D`. Tarjeta principal en azul noche `#123B57`.
- **Pastel por tipo de actividad** (fondo / icono): comida `#FCEBA6`/`#A15C07`, sueño `#E4E1FB`/`#4C46B6`, pañal `#CFE7FB`/`#1D6FB0`, medicina `#F9DDF3`/`#A63A86`, vacuna `#E3F2C4`/`#4D7A12`, cita `#DCE4F2`/`#3A5288`, crecimiento y baño `#D2F1EA`/`#087873`, momentos y juego `#FFE1D1`/`#B4532F`. En gráficos: `#E0A21B`, `#6F67D9`, `#3B8FD9`, `#C2549E`, `#20A99F`.
- **Inicio = dashboard general:** cabecera (bebé, búsqueda, avisos) → sesión activa → **tarjeta principal que abre el Centro de registro** → registro rápido (4 accesos) → resumen del día con donut → próximos eventos en tarjetas pastel desplazables → "Todo sobre el bebé" (cuadrícula de 12 módulos: calendario, citas, vacunas, medicinas, crecimiento, fotos, momentos, reportes, bitácora, alergias, contactos, ajustes).
- **Centro de registro:** sesión activa con cronómetro por lado, "Repetir lo último", y todas las actividades agrupadas en Rutina, Salud y Momentos, con chips para saltar entre grupos.
- **Navegación:** barra flotante en forma de pastilla; el destino activo se muestra en oscuro con su etiqueta, y el + abre el registro.
- **Modo oscuro y nursery:** se mantienen como en las secciones anteriores.
