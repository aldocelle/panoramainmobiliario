# Cobertura territorial nacional

## Estado

El frontend permite seleccionar las 16 regiones. Comuna, sector/localidad y dirección son campos independientes. La dirección privada no se presenta en la ficha pública. El catálogo comunal completo todavía no está integrado.

## Fuentes compartidas

- https://github.com/juanbrujo/listado-apis-publicas-en-chile
- https://docs.boostr.cl/reference/welcome
- https://postal-code-api.kainext.cl/v1/api

Revisión del 21 de octubre de 2026: el listado marca la antigua API de División Político Administrativa como descontinuada. Describe KaiNext con regiones y comunas, pero su documentación no respondió durante la revisión. No se han validado sus respuestas, licencia ni disponibilidad para producción.

## Diseño de integración

1. Verificar proveedor y permisos de reutilización; preferir códigos territoriales oficiales.
2. Importar un catálogo validado y versionado a base propia. No consultar una API externa por cada interacción del usuario.
3. Guardar códigos como texto, nombres oficiales, relaciones región/provincia/comuna, fuente y fecha de revisión.
4. Distinguir comuna de ciudad, localidad y sector. Una comuna puede contener varias localidades.
5. Mantener el último catálogo válido cuando falle una actualización; actualizar mediante una tarea administrativa con registro de cambios.
6. Validar combinaciones en servidor. Incorporar coordenadas y geocodificación por separado, con controles de privacidad de ubicación.

No se debe afirmar que el portal contiene oferta en una comuna solo porque exista en el catálogo. Las páginas geográficas y conteos deben derivarse de propiedades efectivamente publicadas.

## Zonificación comercial de referencia

La imagen aportada posteriormente por el usuario se transcribió a `src/data/reference-zones.json` y `docs/ZONAS_TRANSCRIPCION.md`: 153 entradas (Norte 16, Quinta región 27, Región metropolitana 43 y Sur 67). Se usa únicamente en Planes y cupos → Inmobiliarias y proyectos para consultar y filtrar la zonificación comercial de referencia; no aparece en la publicación individual de corredores. Es un catálogo comercial externo parcial, no una división administrativa ni cobertura oficial del país. No asigna tarifas de Panorama, no completa automáticamente comunas ausentes y no declara vigente la clasificación del competidor. Los alias de búsqueda se mantienen fuera de la transcripción literal.
