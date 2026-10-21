# Panorama Inmobiliario

Portal para venta y arriendo de propiedades en Chile, con un espacio de trabajo diseñado para corredores. Frontend en **Vue 3 + Vite** y API independiente en **PHP 8.3 + MySQL/MariaDB**. El frontend sigue en modo demostración local mientras se conecta a la API.

## Antecedentes del proyecto

Consulta el [documento maestro de Panorama Inmobiliario](docs/ANTECEDENTES_PANORAMA_INMOBILIARIO.md): modelo de negocio, referencias y tarifas del competidor, segmentos, beneficios, APIs, diseño, condiciones propias, estado de implementación y decisiones pendientes.

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

**Es un prototipo funcional en un navegador, no un sistema listo para operar con clientes reales.** Datos ficticios y fotografías ilustrativas de Unsplash. La interfaz de demostración no utiliza todavía la autenticación ni la API del directorio `backend/`; no hay pagos ni envíos de correo/WhatsApp conectados a estas pantallas. El acceso al panel es libre y los registros se guardan en `localStorage`; cada navegador tiene una copia independiente. No introduzcas datos personales reales. El JSON exportado es un respaldo descargable; su importación todavía no está implementada.

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

## Backend PHP

Se implementó una API con 59 rutas: cuentas y roles, sesiones/CSRF, cartera multiempresa, cupos transaccionales, imágenes, favoritos, CRM, agenda, proyectos/unidades, programa de captación, exportaciones y auditoría. Incluye migraciones y herramientas de operación. Ver [inicio y pruebas](backend/README.md), [contrato de API](docs/API_PHP.md) y [despliegue PHP](docs/DESPLIEGUE_PHP.md).

Validación local: 38 pruebas por motor en SQLite y MariaDB, concurrencia con dos procesos, HTTP real y pruebas del cliente JavaScript. GitHub Actions queda configurado; su resultado remoto debe comprobarse en GitHub.

## Próxima fase

Conectar el frontend a la API; catálogo territorial completo y validado; búsqueda geográfica; entrega de notificaciones; tarifas propias, planes y facturación. La integración con APIs de terceros requiere comprobar disponibilidad, condiciones y límites antes de operar.

## Planes y condiciones en preparación

En **Planes y cupos** puedes probar capacidades de 5, 30 y 100 publicaciones activas. Precios de Panorama pendientes; no hay contratación, cobros ni renovación. Los límites se comprueban en la publicación y al cambiar estados; los borradores no consumen cupos. La selección de capacidad se guarda solo como simulación local. La pantalla **Condiciones** es un borrador original de producto, sin aceptación contractual. Ver [especificación de planes](docs/PLANES.md).

Las condiciones propias de Panorama se encuentran en [Términos y condiciones](docs/TERMINOS_Y_CONDICIONES.md), con [decisiones de adaptación](docs/ADAPTACION_CONDICIONES.md). El contenido de la pantalla y el documento comparten `src/data/terms.json`; regenerar con `node scripts/export-terms.mjs`.

En Planes y cupos también está la consulta de [zonas comerciales transcritas de la imagen](docs/ZONAS_TRANSCRIPCION.md). Se conserva como referencia externa: no sustituye el catálogo territorial ni determina tarifas propias.

**Marca y beneficios** permite configurar nombre/logo para las publicaciones y descargar un reporte CSV actual de cartera. Informes trimestrales y webinars aparecen como beneficios previstos, aún no disponibles. Ver [beneficios para corredores](docs/BENEFICIOS_CORREDORES.md).

Como propuesta futura de captación de propietarios se documentó [Conecta con un corredor](docs/PROGRAMA_CONECTA_CORREDOR.md), adaptando los antecedentes del programa compartido por el usuario. La API PHP implementa directorio, alta/baja y captación; falta conectar sus pantallas y desplegarlo. No hay recepción de solicitudes reales en el prototipo.

La referencia de [paquetes de publicación individual](docs/PUBLICACIONES_INDIVIDUALES_REFERENCIA.md) y los formularios competitivos de alta/baja del programa están recogidos en la documentación maestra, distinguiendo fecha de referencia, propuestas e implementación pendiente.
