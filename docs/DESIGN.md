# Dirección de diseño — Panorama Inmobiliario

## Idea

Una perspectiva clara del lugar donde quieres vivir y del negocio que quieres construir. Portal público de carácter editorial, espacio del corredor orientado a tareas reales.

## Sistema visual

- Azul marino `#0F2C59`: acciones principales, precios y marca. Azul profundo `#0A1B33`: barra lateral del corredor, banda final y pie.
- Dorado `#F2B33D`: acento escaso, solo sobre fondos oscuros (palabra destacada del titular, ítem activo, pendientes, botón de la banda final). Nunca como texto sobre blanco.
- Azul `#1E5BC6`: palabra destacada sobre fondo claro y etiqueta "Arriendo"; verde `#1F8A4C` para "Venta".
- Fondo `#F5F7FB`, tarjetas `#FFFFFF`, texto `#0F1B2D`, secundario `#5B6577` (contraste AA).
- DM Sans para interfaz y Manrope para títulos y precios. Fuentes del sistema como respaldo.
- Portada a todo lo ancho con foto, buscador blanco sobre la foto, franja de beneficios, tarjetas con tipo y operación, precio dominante, specs con íconos y flecha redonda; sección de corredoras con su cartera.
- Radios de 14 px en tarjetas, botones en píldora, sombras suaves. Íconos de línea propios (trazo 1.8) en `src/components/Icon.vue`.
- Movimiento: entrada única de la portada y escalonado de tarjetas públicas; el panel carga directo a la tarea. Respetar `prefers-reduced-motion`.
- Estados expresados con palabras. Foco de teclado visible (dorado sobre fondos oscuros), etiquetas de formulario y enlace para saltar al contenido.

## Experiencias

Portal: buscar → evaluar ficha → consultar. Panel: revisar pendientes → administrar cartera → responder interesados → coordinar visitas. Las métricas se calculan sobre registros locales, sin estadísticas comerciales simuladas.

## Responsive

Portada: cuatro columnas en escritorio, tres bajo 1100 px, dos en tablet y una en móvil. Panel: tres columnas. Menú lateral desplegable en móvil, formularios adaptados al ancho y diálogos con desplazamiento. Respetar movimiento reducido.

## Criterios para continuar

No agregar botones sin comportamiento ni indicadores de rendimiento inventados. Antes de incorporar módulos nuevos, verificar estados vacíos, errores de guardado, navegación por teclado y recorrido completo en celular. El prototipo no sustituye controles de acceso del servidor.
