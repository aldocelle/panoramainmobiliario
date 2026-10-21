# Panorama Inmobiliario

Portal para venta y arriendo de propiedades en Chile, con un espacio de trabajo diseñado para corredores. Primera versión funcional de frontend en **Vue 3 + Vite**.

## Ejecutar

Requiere Node.js 22.12+ o 24 LTS.

```bash
npm ci
npm run dev
```

```bash
npm test
npm run build
npm run preview
```

## Recorrido implementado

1. Entra a **Soy corredor → Nueva propiedad**.
2. Completa ubicación, precio, características, fotografía HTTPS y descripción.
3. Guarda como borrador o publica. Los borradores no aparecen en el portal público.
4. Abre el portal, filtra por región, operación, tipo o comuna y consulta una propiedad.
5. La consulta aparece en **Interesados**: actualiza su estado, registra notas y agenda una visita futura.
6. Revisa la agenda y el resumen del corredor. Exporta un respaldo JSON desde el panel.

También incluye edición, cambios de estado, favoritos y recuperación de borradores de creación.

## Alcance real de esta entrega

**Es un prototipo funcional en un navegador, no un sistema listo para operar con clientes reales.** Datos ficticios y fotografías ilustrativas de Unsplash. No hay autenticación, backend, pagos ni envíos de correo/WhatsApp. El acceso al panel es libre y los registros se guardan en `localStorage`; cada navegador tiene una copia independiente. No introduzcas datos personales reales. El JSON exportado es un respaldo descargable; su importación todavía no está implementada.

Se incluyen las 16 regiones con códigos como texto. La comuna y el sector se ingresan manualmente. No se presenta un catálogo incompleto como cobertura validada de todas las comunas. Ver [plan territorial](docs/TERRITORIO.md).

Las fotografías y Google Fonts necesitan conexión; la interfaz tiene colores y fuentes de respaldo. La foto se incorpora por URL HTTPS: la carga de archivos y las galerías quedan para la siguiente fase.

## Estructura

- `src/components/`: tarjetas, iconos y asistente de publicación.
- `src/data/`: ejemplos y catálogo regional.
- `src/lib/domain.js`: búsqueda, validación y recuperación de estado.
- `src/App.vue`: portal y espacio del corredor.
- `src/style.css`: sistema visual y adaptación móvil.
- `tests/`: reglas del dominio.
- `docs/`: dirección visual, alcance y desarrollo posterior.

## Próxima fase

Backend con base de datos y migraciones; autenticación y aislamiento por corredora; almacenamiento de imágenes; catálogo territorial completo y validado; búsqueda geográfica; gestión de propietarios; consentimiento y tratamiento de datos; notificaciones e integraciones autorizadas; planes y facturación. La integración con APIs de terceros requiere comprobar disponibilidad, condiciones y límites antes de operar.

## Planes y condiciones en preparación

En **Planes y cupos** puedes probar capacidades de 5, 30 y 100 publicaciones activas. Precios de Panorama pendientes; no hay contratación, cobros ni renovación. Los límites se comprueban en la publicación y al cambiar estados; los borradores no consumen cupos. La selección de capacidad se guarda solo como simulación local. La pantalla **Condiciones** es un borrador original de producto, sin aceptación contractual. Ver [especificación de planes](docs/PLANES.md).

Las condiciones propias de Panorama se encuentran en [Términos y condiciones](docs/TERMINOS_Y_CONDICIONES.md), con [decisiones de adaptación](docs/ADAPTACION_CONDICIONES.md). El contenido de la pantalla y el documento comparten `src/data/terms.json`; regenerar con `node scripts/export-terms.mjs`.

En Planes y cupos también está la consulta de [zonas comerciales transcritas de la imagen](docs/ZONAS_TRANSCRIPCION.md). Se conserva como referencia externa: no sustituye el catálogo territorial ni determina tarifas propias.

**Marca y beneficios** permite configurar nombre/logo para las publicaciones y descargar un reporte CSV actual de cartera. Informes trimestrales y webinars aparecen como beneficios previstos, aún no disponibles. Ver [beneficios para corredores](docs/BENEFICIOS_CORREDORES.md).
