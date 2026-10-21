# Panorama Inmobiliario — antecedentes y definición del proyecto

**Fecha de consolidación:** 21 de octubre de 2026.
**Repositorio:** https://github.com/aldocelle/panoramainmobiliario
**Rama de trabajo:** `feat/base-portal-corredor`.
**Revisión de implementación:** https://github.com/aldocelle/panoramainmobiliario/pull/1

Este documento reúne los antecedentes aportados por el usuario, la investigación realizada y las decisiones de producto tomadas durante la definición de Panorama Inmobiliario. Distingue referencias de la competencia, propuestas propias, funciones de demostración y trabajo pendiente. No establece tarifas definitivas, compromisos comerciales vigentes ni una certificación legal.

## 1. Identidad, alcance y objetivo

El nombre elegido por el usuario es **Panorama Inmobiliario**. El proyecto contempla venta y arriendo de propiedades en **todo Chile**, sin limitarse a Viña del Mar, Valparaíso o Concón. Tener estructura territorial nacional no significa disponer de oferta publicada en todas las comunas.

Objetivo: conectar personas que buscan propiedades con propietarios, corredores, corredoras e inmobiliarias, y entregar herramientas que ahorren trabajo al anunciante y permitan sostener ingresos recurrentes para la plataforma.

La prioridad expresada por el usuario es prestar especial atención al **diseño frontend del cliente profesional, especialmente el corredor de propiedades**. El panel debe ser útil para trabajar diariamente y justificar una suscripción mediante organización, ahorro de tiempo y oportunidades comerciales medibles.

## 2. Públicos y experiencias

| Segmento | Necesidad principal | Experiencia prevista |
| --- | --- | --- |
| Comprador o arrendatario | Encontrar, evaluar, comparar y contactar | Portal público, filtros, fichas, favoritos y consultas |
| Propietario particular | Publicar una oferta y recibir interesados | Publicación y gestión acotadas; política comercial pendiente |
| Corredor o corredora | Administrar propiedades, consultas y visitas | Panel de trabajo, cartera, seguimiento y planes por cupos |
| Equipo de corretaje | Coordinar agentes y oportunidades | Espacio de organización con usuarios y roles; pendiente |
| Inmobiliaria o desarrollador | Promover proyectos nuevos y sus unidades | Oferta separada por proyecto, zona y exposición; en preparación |

**Distinción confirmada por el usuario:** las zonas comerciales y la matriz de descuentos de proyectos corresponden a **inmobiliarias**, no a los planes de propiedades individuales de corredores.

## 3. Modelo de negocio de referencia: Portalinmobiliario.com

La marca analizada es **Portalinmobiliario.com**, de Mercado Libre. El modelo investigado combina planes de publicación, mayor exposición y publicidad para anunciantes; la búsqueda de propiedades es gratuita para las personas interesadas. El cobro por avisaje no depende del cierre de la compraventa o arriendo.

| Fuente de ingresos de referencia | Cliente que paga | Valor ofrecido |
| --- | --- | --- |
| Plan mensual de publicaciones | Corredores y corredoras | Capacidad contratada para anunciar su cartera |
| Exposición y destacados | Anunciantes | Mayor visibilidad de avisos y marca |
| Planes para proyectos nuevos | Inmobiliarias | Promoción de proyectos con distintos niveles de exposición |
| Publicidad digital | Inmobiliarias | Banners y presencia comercial dentro del portal |

Las páginas revisadas también mencionan publicación en Portalinmobiliario.com y Mercado Libre, logo/marca, reportes y formación. Estos beneficios del competidor no significan que Panorama tenga integración, audiencia o acuerdos con esas empresas.

## 4. Tabla aportada: planes Plata para corredores del competidor

Transcripción de la tabla compartida por el usuario. **Valores mensuales en UF, sin IVA.** Es una referencia comercial externa, no una lista de precios de Panorama. Su vigencia deberá confirmarse al utilizarla en una comparación comercial.

| Hasta cuántas publicaciones | UF mensuales sin IVA |
| ---: | ---: |
| 5 | 1,11 |
| 10 | 1,33 |
| 20 | 2,11 |
| 30 | 2,22 |
| 50 | 3,11 |
| 75 | 3,22 |
| 100 | 3,33 |
| 150 | 4,44 |
| 200 | 5,55 |
| 350 | 9,77 |
| 500 | 13,88 |
| 750 | 20,87 |
| 1.000 | 27,75 |
| 1.500 | 48,29 |
| 2.000 | 64,38 |
| 2.500 | 80,48 |
| 3.000 | 96,57 |
| 4.000 | 128,76 |
| 5.000 | 160,95 |
| 6.000 | 193,14 |
| 7.000 | 225,33 |
| 8.000 | 257,52 |
| 9.000 | 289,71 |
| 10.000 | 321,90 |
| 12.000 | 386,28 |
| 14.000 | 450,66 |
| 16.000 | 515,04 |
| 18.000 | 579,42 |
| 20.000 | 643,80 |
| 22.000 | 708,18 |
| 24.000 | 772,56 |

Aprendizajes del análisis de esa tabla:

- Se paga por la capacidad del plan; utilizar menos cupos aumenta el costo efectivo por aviso utilizado.
- Algunos saltos de capacidad tienen diferencias pequeñas, por ejemplo de 5 a 10 avisos: 0,22 UF; de 75 a 100: 0,11 UF.
- El costo unitario no baja de forma continua: 1.000 cupos cuestan 0,02775 UF por cupo utilizado a plena capacidad; 10.000 cuestan 0,03219 UF por cupo.
- La tabla corresponde a Plata. No describe por sí sola el costo de destacados, campañas u otras prestaciones.
- No se debe multiplicar el valor de un plan por una conversión de UF inventada ni omitir impuestos al mostrar el total.

Fuente de referencia: https://www.portalinmobiliario.com/pi/home/info/corredoras

## 5. Propuesta propia para corredores

Se implementó una **simulación de capacidades**, no una contratación comercial:

| Plan propuesto | Publicaciones activas | Precio de Panorama | Estado |
| --- | ---: | --- | --- |
| Inicio | 5 | Por definir | Simulación local |
| Profesional | 30 | Por definir | Simulación local |
| Equipo | 100 | Por definir | Simulación local |
| Corporativo | Por definir según cartera | Por definir | Propuesta pendiente |

Reglas del prototipo:

1. Solo el estado **Publicada** ocupa cupos simultáneos.
2. Borrador, Pausada, Vendida y Arrendada no cuentan como publicaciones activas.
3. Editar un aviso existente no consume un cupo adicional.
4. El asistente y el cambio de estado desde la ficha comprueban capacidad antes de publicar.
5. Al llenarse el plan, se pueden conservar borradores y liberar un cupo o cambiar la capacidad simulada.
6. No se permite reducir el plan por debajo de la cantidad de avisos activos. No se borran ni se retiran avisos automáticamente.
7. La selección de capacidad se guarda en el navegador; no autoriza cobros, activa una suscripción ni genera una renovación.
8. Los controles locales deberán trasladarse al servidor, con comprobación de permisos y concurrencia, antes de vender el servicio.

Detalles: [PLANES.md](PLANES.md). Configuración: `src/data/plans.js`.

## 6. Inmobiliarias y proyectos nuevos

Para inmobiliarias se propone una estructura comercial distinta: **proyecto + zona comercial + nivel de exposición**. Una ficha de proyecto puede contener varias unidades y tipologías; proyecto, unidad y aviso individual son conceptos diferentes.

Niveles propios propuestos: **Esencial**, **Destacado** y **Proyección**. Sus precios, espacios publicitarios y descuentos no están definidos. Se muestran como propuesta sin contratación ni campañas activas.

### 6.1. Matriz de descuentos transcrita del competidor

Texto de la captura: «Total de proyectos publicados por tu inmobiliaria» y «Cantidad de tus proyectos destacados (Planes ORO y ORO PREMIUM)».

| Proyectos destacados | 1–5 proyectos totales | 6–15 proyectos totales | 16–29 proyectos totales | 30+ proyectos totales |
| --- | --- | --- | --- | --- |
| 0–2 | — | — | 5 % | 10 % |
| 3–5 | 5 % | 5 % | 10 % | 15 % |
| 6–15 | — | 10 % | 10 % | 15 % |
| 16+ | — | — | 15 % | 20 % |

La captura no indica vigencia ni base monetaria del descuento. «—» se conserva como ausencia de un porcentaje especificado, no como 0 %. No se permite calcular con más destacados que proyectos totales. La consulta implementada devuelve el porcentaje de referencia; **no descuenta precios de Panorama**.

### 6.2. Zonas comerciales de la primera imagen

Se transcribieron **153 entradas**:

| Agrupación de la imagen | Cantidad | Denominación comercial |
| --- | ---: | --- |
| Norte | 16 | Norte/Sur - Zona 1, 2 o 3 |
| Quinta región | 27 | Centro - Zona 1, 2 o 3 |
| Región metropolitana | 43 | RM - Zona 1, 2 o 3 |
| Sur | 67 | Norte/Sur - Zona 1, 2 o 3 |

La transcripción íntegra está en [ZONAS_TRANSCRIPCION.md](ZONAS_TRANSCRIPCION.md); datos estructurados en `src/data/reference-zones.json`. Se conservan las abreviaturas de la imagen y se agregan alias únicamente para facilitar búsquedas.

Este catálogo es parcial y comercial: no equivale a regiones administrativas ni a todas las comunas de Chile. Una comuna ausente queda sin clasificación en la referencia, no se le asigna Zona 3. Se consulta exclusivamente en la pestaña de inmobiliarias y se retiró del formulario de avisos individuales.

Detalle del segmento: [INMOBILIARIAS.md](INMOBILIARIAS.md).

## 7. Beneficios para corredores

El usuario pidió incorporar beneficios equivalentes en propósito a los ofrecidos por la competencia:

- Marca y logo en cada publicación.
- Informes trimestrales.
- Capacitaciones webinar.
- Reportes de publicaciones.

| Beneficio propio | Estado real de Panorama |
| --- | --- |
| Nombre y logo de la marca en tarjetas y fichas | Funciona en la demostración; configuración local, una marca por espacio demo |
| Reporte de publicaciones | Descarga CSV de la cartera actual con código, título, comuna, operación, estado, moneda y precio |
| Resumen por estado | Conteos actuales, calculados desde los registros locales |
| Informes trimestrales | Previstos; requieren historial verificable y proceso de generación/entrega |
| Webinars y formación | Previstos; no existe aún calendario, facilitador confirmado ni acceso a grabaciones |

No se inventan visitas, conversiones ni resultados históricos. El CSV actual no se presenta como informe trimestral. Los iconos de la competencia no se copiaron; se mantuvo el sistema gráfico propio.

Detalle: [BENEFICIOS_CORREDORES.md](BENEFICIOS_CORREDORES.md).

## 8. Herramientas para ahorrar trabajo y monetización posible

La siguiente tabla recoge propuestas discutidas. La columna de estado evita confundirlas con una implementación completa.

| Herramienta | Ahorro o valor para el corredor | Ingreso propuesto | Estado |
| --- | --- | --- | --- |
| Publicación guiada | Ordena datos y reduce avisos incompletos | Suscripción | Demo funcional |
| Asistente de descripciones | Borradores de títulos y textos desde datos reales | Créditos incluidos y paquetes | Pendiente |
| Gestión de fotografías | Ordenar, comprimir y preparar imágenes | Plan; servicios profesionales extra | Una foto por URL; carga/galería pendientes |
| CRM de interesados | Centralizar consultas, notas y estados | Suscripción | Demo local básica |
| Recordatorios de seguimiento | Evitar contactos olvidados | Plan profesional | Pendiente |
| Agenda de visitas | Organizar coordinación | Plan | Demo local; sin invitaciones externas |
| Confirmaciones y recordatorios | Reducir coordinación y ausencias | Módulo con límites de consumo | Pendiente |
| WhatsApp integrado | Asociar conversaciones y usar respuestas preparadas | Integración y consumo | Pendiente |
| Captación de propietarios | Recibir encargos de venta o arriendo | Plan superior | Pendiente |
| Dossier de propiedad | Generar presentación PDF o enlace | Plan profesional | Ficha local; PDF pendiente |
| Piezas para redes | Crear materiales con precio, fotos y marca | Plan o paquetes | Pendiente |
| Informe para propietario | Resumir consultas, visitas y gestiones | Plan profesional | Pendiente; CSV de cartera no lo sustituye |
| Web propia del corredor | Presencia profesional con cartera sincronizada | Implementación y mensualidad | Pendiente |
| Gestión documental | Ordenar antecedentes y detectar faltantes | Plan avanzado | Pendiente |
| Trabajo de equipo | Asignación y coordinación de agentes | Usuarios o capacidad adicional | Pendiente |
| Publicación en otros portales | Reducir carga y actualización repetidas | Módulo adicional | Sujeto a acuerdos e integración; pendiente |

Fuentes de ingresos propuestas para Panorama:

1. Suscripción mensual por herramientas y capacidad de publicación.
2. Implementación, migración de cartera y capacitación inicial.
3. Web profesional del corredor con cargo inicial y mensualidad.
4. Fotografía, video, recorridos virtuales y contenido por propiedad.
5. Consumos adicionales de mensajería, generación de contenido o almacenamiento.
6. Avisos destacados cuando exista audiencia y exposición medibles.
7. Campañas para inmobiliarias y proyectos, con entregables e indicadores definidos.
8. Derivaciones de servicios con acuerdos transparentes y habilitación para compartir datos.

Prioridad propuesta: **cartera + interesados + agenda + seguimiento + web del corredor**. La utilidad de gestión puede aportar valor antes de que el portal tenga una audiencia grande. No se ha validado cuánto pagaría el cliente por este paquete.

## 9. Investigación de usuarios: tres preguntas y hallazgos

Preguntas de descubrimiento planteadas:

1. ¿Usas estos portales para buscar propiedades o para publicarlas? ¿Cuáles utilizas y por qué los eliges?
2. Pensando en la última vez que usaste uno, ¿qué fue lo más difícil o qué te hizo perder más tiempo?
3. ¿Pagas actualmente por alguno? ¿Qué tendría que ofrecerte un portal nuevo para que decidieras pagar por él?

Se realizó investigación documental, no entrevistas propias ni una encuesta representativa. Las respuestas reconstruidas son hipótesis y hallazgos de fuentes, no testimonios inventados de clientes.

| Pregunta | Evidencia encontrada | Implicancia para Panorama |
| --- | --- | --- |
| Qué utilizan y por qué | Testimonios mencionan confianza en portales conocidos; la FNE describe la relevancia comercial de Portalinmobiliario.com para corredores | Facilidad para buscar y prueba de consultas útiles para anunciantes |
| Qué les quita tiempo | Un testimonio describe búsqueda tediosa y alertas deficientes; la FNE recoge valor de la gestión centralizada y multipublicación | Búsquedas guardadas, avisos actualizados y menor duplicación de trabajo |
| Qué opacidad encuentran | Una reclamación individual cuestiona cómo se calcula un indicador de respuesta y su relación con WhatsApp | Indicadores explicables, historial de eventos y posibilidad de revisión |
| Por qué pagarían | Hay contratación real de planes de publicación y servicios de integradoras; no se determinó disposición a pagar por Panorama | Validar ahorro de tiempo, calidad de consultas y precio mediante pilotos |

La FNE de 2025 aporta evidencia del mercado y de antecedentes de años anteriores; no debe presentarse como medición actual de participación en octubre de 2026. Los reclamos y Reddit son señales cualitativas individuales, sin verificación independiente ni estimación de frecuencia.

Como referencia adicional de precio, la página de ChilePropiedades consultada durante la conversación mostraba 30 propiedades y 5 destacados por $3.990 mensuales. No es una tarifa de Panorama y deberá verificarse antes de reutilizar una comparación.

## 10. Cobertura territorial y APIs compartidas

Fuentes compartidas por el usuario:

- https://docs.boostr.cl/reference/pharmacies-info
- https://docs.boostr.cl/reference/welcome
- https://github.com/juanbrujo/listado-apis-publicas-en-chile

Para el portal se identificaron referencias de datos geográficos:

| Fuente | Información descrita | Situación al revisar |
| --- | --- | --- |
| API División Político Administrativa | Regiones, provincias y comunas | El listado la marcaba como descontinuada |
| KaiNext Postal Code API | Regiones, comunas y códigos postales | El listado indica 16 regiones y 346 comunas; documentación no respondió y no se validó su servicio |
| Boostr | Servicios agregados mediante APIs | Compartida para explorar; no se confirmó un endpoint territorial operativo para el portal |

Distinguir **región, provincia, comuna, ciudad/localidad y sector/barrio**. Un catálogo de comunas no sustituye a uno de localidades. Las coordenadas y la geocodificación de direcciones son otra integración.

Decisión técnica propuesta: importar un catálogo autorizado y validado a base propia, con códigos textuales, fuente, fecha y versión; mantener el último catálogo válido cuando falle una actualización. No depender de una llamada externa por cada selección del usuario.

Estado real: están incluidas las 16 regiones; comuna y sector se ingresan manualmente. La integración nacional de comunas y localidades está pendiente. Detalle: [TERRITORIO.md](TERRITORIO.md).

## 11. Diseño y experiencia de usuario

Dos experiencias con identidad compartida:

- **Portal público:** buscar, evaluar ficha y consultar.
- **Panel del corredor:** revisar pendientes, administrar cartera, responder interesados y coordinar visitas.

El recorrido prioritario es **crear propiedad → publicar → recibir interesado → agendar visita**. El diseño debe facilitarlo en escritorio y celular.

Dirección visual implementada: verde bosque `#173F37`, acentos lima `#D8ED99`, fondo mineral `#F6F7F3`, blanco y texto oscuro. DM Sans para interfaz, Manrope para títulos y un acento editorial en Georgia cursiva. Fotografías amplias, jerarquía clara, bordes discretos y espacios generosos.

Se incluyen foco de teclado visible, etiquetas de formularios, estados textuales, diálogos con control de foco y preferencia de movimiento reducido. La adaptación responsive está implementada en CSS; aún falta revisión visual completa en dispositivos móviles. No equivale a una auditoría de accesibilidad certificada.

Fotografías ilustrativas desde Unsplash y fuentes desde Google Fonts necesitan conexión; existen fondos y tipografías de respaldo. Detalle: [DESIGN.md](DESIGN.md).

## 12. Condiciones propias y adaptación del documento de referencia

El usuario compartió el enlace de condiciones del competidor y luego el archivo completo `Markdown pegado(1).md`, cuya versión indicada era 19/06/2025. Tras leerlo completo se elaboró un texto propio, sin limitarse a cambiar la marca.

Resultado: **23 secciones** en [TERMINOS_Y_CONDICIONES.md](TERMINOS_Y_CONDICIONES.md). La pantalla utiliza la misma fuente `src/data/terms.json`, ofrece índice y descarga. Para regenerar el Markdown: `node scripts/export-terms.mjs`.

Decisiones principales:

- Independencia de Mercado Libre y de sus cuentas, servicios y ecosistema de datos.
- Sin facultad general para retirar fondos desde Mercado Pago o cuentas bancarias.
- Sin autorización de reporte a DICOM incorporada a Panorama.
- Sin equiparar una consulta o visita con obligación de comprar, arrendar o pagar una comisión.
- Derechos del anunciante sobre fotografías y autorización limitada al servicio.
- Sin renuncia general al retracto, a devoluciones o a responsabilidades imputables a la plataforma.
- Moderación proporcional, comunicación de motivos y posibilidad de revisión.
- Uso delimitado de datos de interesados y política de privacidad propia pendiente.
- Diferenciación de planes de corredores y proyectos nuevos de inmobiliarias.
- Propuesta de ciclo mensual desde activación, cancelación de próxima renovación sin antelación adicional de diez días, aviso de cambios materiales con 30 días y ventana de exportación de 30 días al término. Son políticas propuestas que requieren implementación y validación; no capacidades actuales.

El borrador no está vigente ni se solicita su aceptación. Faltan razón social o titular legal, RUT, domicilio, representación cuando corresponda, dominio confirmado y contactos reales. No se completaron esos datos mediante supuestos sobre el usuario.

Se consultaron SERNAC y BCN sobre información comercial, retracto y protección de datos. La vigencia normativa debe verificarse al lanzamiento; el texto no afirma cumplimiento integral. Política de privacidad, acuerdos de tratamiento, condiciones de campañas y reglas de cobro deben cerrarse junto con la operación real.

Detalle de decisiones y fuentes: [ADAPTACION_CONDICIONES.md](ADAPTACION_CONDICIONES.md).

## 13. Estado técnico e implementación

Stack actual: **Vue 3 + Vite**, frontend estático con componentes y lógica de dominio. El código está en la rama indicada y propuesto en el pull request; no se ha fusionado a `main` ni desplegado como servicio comercial durante esta conversación.

| Implementado en la demostración | Pendiente para operar |
| --- | --- |
| Portal con filtros y favoritos | Búsqueda geográfica y catálogo territorial completo |
| Fichas y consultas locales | Entrega real de consultas, antispam y protección de datos |
| Cartera y asistente de publicación | Backend, base de datos y almacenamiento seguro |
| Interesados con notas y estados | Cuentas, roles y aislamiento entre organizaciones |
| Agenda local | Zona horaria explícita, duración, solapamientos y notificaciones |
| Planes con cupos simulados | Contratación, cobros, facturación y permisos en servidor |
| Marca en avisos y reporte CSV | Múltiples marcas/organizaciones e informes históricos |
| Consulta de matrices de referencia | Tarifas propias y campañas reales |
| Condiciones en borrador | Identidad del operador y documentos definitivos |

Límites relevantes: `localStorage` por navegador, sin autenticación; cada dispositivo tiene una copia independiente. El panel es de acceso libre. No debe usarse para información personal real. El respaldo JSON tiene descarga pero no importación. Las fotos se agregan por URL; no hay carga de archivos ni galerías.

Verificaciones realizadas antes de esta consolidación: **18 pruebas de lógica**, compilación de producción y comprobación de diferencias sin errores. Se revisaron en navegador publicación, agenda, consulta de zonas, separación de inmobiliarias, matriz y guardado de marca. No se afirma prueba integral de todas las pantallas o dispositivos.

## 14. Prioridades siguientes y decisiones abiertas

1. Validar el paquete de herramientas con corredores: tareas actuales, horas perdidas, alternativas utilizadas y disposición real a pagar.
2. Definir tarifas propias, capacidad, beneficios y costos de servicio sin trasladar automáticamente los precios del competidor.
3. Implementar backend, base de datos, cuentas, recuperación, permisos y separación por organización.
4. Validar e importar el catálogo territorial; incorporar localidad, sectores y mapas con privacidad de direcciones.
5. Completar carga y gestión de imágenes, consultas y seguimiento con datos persistentes.
6. Construir el ciclo comercial: resumen contractual, pago autorizado, facturación, renovación, cambios y cancelación.
7. Definir entidad operadora, política de privacidad y documentos contractuales revisados.
8. Establecer medición de consultas y exposición antes de vender destacados o prometer rendimiento.
9. Preparar informes trimestrales y un calendario real de formación antes de ofrecerlos como disponibles.
10. Completar revisión móvil, accesibilidad y pruebas del recorrido antes de un piloto con personas reales.

No se han decidido todavía precios de Panorama, proveedor de pagos, dominio definitivo, proveedor territorial operativo, calendario de webinars, métricas comerciales, tarifa de proyectos, descuentos propios ni proveedores de servicios complementarios.

## 15. Fuentes y documentos de referencia

### Aportados por el usuario

- Tabla de planes Plata de corredores, reproducida en la sección 4.
- https://www.portalinmobiliario.com/pi/home/info/condiciones#item1
- Archivo `Markdown pegado(1).md`: condiciones atribuidas al competidor, versión indicada 19/06/2025.
- Imagen `image(20261021-165720).png`: zonas comerciales por comuna, transcritas en el documento enlazado.
- Imagen `Screenshot 2026-10-21 at 13.56.18.png`: matriz de descuentos para inmobiliarias, reproducida en la sección 6.
- Beneficios competitivos: marca y logo, informes trimestrales, capacitaciones webinar y reporte de publicaciones.
- APIs enlazadas en la sección 10.

### Consultados durante la investigación

- Planes de corredores: https://www.portalinmobiliario.com/pi/home/info/corredoras
- Planes para inmobiliarias: https://www.portalinmobiliario.com/pi/home/info/inmobiliarias
- Publicidad de proyectos: https://www.portalinmobiliario.com/h/blog/potencia-tu-visibilidad-inmobiliaria-en-portalinmobiliario-com
- FNE, informe de archivo de investigación, 2025: https://www.fne.gob.cl/wp-content/uploads/2025/07/inpu_004_2025-Rol-2739-23.pdf
- ChilePropiedades, planes: https://chilepropiedades.cl/planes-publicar-propiedad
- TOCTOC, publicación: https://www.toctoc.com/publicar
- Testimonio sobre portales: https://www.reddit.com/r/Santiago/comments/1kv3ll2/
- Testimonio sobre alertas: https://www.reddit.com/r/chile/comments/1wbw68o/pagina_de_alerta_de_propiedades_y_autos/
- Reclamo individual sobre indicador de respuesta: https://www.reclamos.cl/reclamo/2026/jun/portal-inmobiliario-mercado-libre-portal-inmobiliario-mercado-libre-muestra-una-calificaci-
- SERNAC, comercio electrónico: https://www.sernac.cl/604/w3-propertyvalue-20982.html
- SERNAC, retracto: https://www.sernac.cl/portal/617/w3-propertyvalue-64530.html
- BCN, Ley 21.719: https://www.bcn.cl/leychile/Navegar?idNorma=1209272&idVersion=2026-12-01

La fuente original de condiciones devolvió 403 al recuperarse directamente; el texto completo se obtuvo después mediante el archivo aportado por el usuario. La documentación de KaiNext no respondió. Estas limitaciones no se suplieron atribuyendo al servicio textos de otros dominios.

## 16. Mapa de documentación del repositorio

| Documento | Contenido |
| --- | --- |
| Este documento | Antecedentes consolidados, referencias, alcance y decisiones |
| [DESIGN.md](DESIGN.md) | Dirección visual y criterios de experiencia |
| [PLANES.md](PLANES.md) | Cupos y contratación pendiente |
| [INMOBILIARIAS.md](INMOBILIARIAS.md) | Segmento de proyectos y matriz de descuentos |
| [ZONAS_TRANSCRIPCION.md](ZONAS_TRANSCRIPCION.md) | Las 153 entradas completas de la imagen |
| [BENEFICIOS_CORREDORES.md](BENEFICIOS_CORREDORES.md) | Marca, reporte y programa de beneficios |
| [TERMINOS_Y_CONDICIONES.md](TERMINOS_Y_CONDICIONES.md) | Documento contractual propio en borrador |
| [ADAPTACION_CONDICIONES.md](ADAPTACION_CONDICIONES.md) | Cambios de fondo y fuentes legales consultadas |
| [TERRITORIO.md](TERRITORIO.md) | Modelo geográfico y estrategia de integración |
| [ROADMAP.md](ROADMAP.md) | Próximas capacidades de desarrollo |

Al continuar el proyecto debe actualizarse este documento cuando cambien precios, funciones, condiciones o decisiones. Las fuentes externas describen la referencia consultada y requieren una nueva verificación antes de atribuirles vigencia futura.

## 17. Programa de conexión entre propietarios y corredores

El usuario incorporó después una referencia adicional de **Elige tu Corredor** de Portalinmobiliario.com: programa para propietarios que desean vender o arrendar y seleccionan profesionales para ser contactados. El texto aportado consta de 14 preguntas y respuestas y alterna el nombre con «Encuentra tu Corredor» en una respuesta. No se afirma su vigencia actual mediante una nueva verificación.

Antecedentes recogidos: formulario del propietario con datos de contacto y propiedad; selección de uno o más corredores; aceptación o rechazo de solicitudes; inscripción y salida voluntarias del corredor; programa presentado como gratuito con comisión inmobiliaria variable; alcance de casas y departamentos; coincidencia según zona y segmento de precio; ausencia de plazo de respuesta en la referencia; contacto alternativo por WhatsApp/teléfono; alternativa de publicación directa sin coincidencias; encuesta de satisfacción por correo.

**Propuesta propia, aún no implementada: «Conecta con un corredor».** Agregaría captación de propiedades para corredores, diferenciada de las consultas de compradores o arrendatarios por avisos publicados. Debe incluir selección explícita de destinatarios, perfiles, cobertura, disponibilidad, estados de invitación, pausa y retiro desde el panel, seguimiento y métricas verificables.

Quedan por validar el costo de participación profesional, un límite de candidatos, el objetivo de respuesta, las reglas de reputación y las opciones de monetización. No se adoptó una comisión de corretaje ni un cobro por contactos; tampoco se considera que solicitar contacto equivalga a firmar un mandato o aceptar honorarios. La zonificación comercial de proyectos nuevos no determina automáticamente la cobertura de estos profesionales.

El detalle de las 14 respuestas de referencia, la adaptación propia, el flujo, datos, estados, indicadores y requisitos está en [PROGRAMA_CONECTA_CORREDOR.md](PROGRAMA_CONECTA_CORREDOR.md). No se enviaron datos ni se utilizaron los formularios de inscripción/desinscripción del competidor.

## 18. Paquetes individuales de publicación y arriendo temporal

El usuario aportó una referencia adicional titulada «Nuevos costos para publicar un inmueble», con fecha de vigencia indicada **1 de abril de 2022**. Se registra como antecedente aportado, no como tarifa actual comprobada.

| Producto | Oro Premium | Oro | Plata |
| --- | --- | --- | --- |
| Exposición | Máxima | Alta | Media |
| Venta/arriendo: duración inicial | 30 días | 30 días | 30 días |
| Venta/arriendo: costo mostrado con IVA | $75.519 | $37.760 | $18.880 |
| Arriendo temporal: duración | 90 días | 30 días | 30 días |
| Arriendo temporal: costo mostrado con IVA | $75.519 | $37.760 | $18.880 |

En venta/arriendo el Premium describe republicaciones sin costo adicional durante un máximo de un año, con una ventana de hasta 30 días tras finalizar el aviso. El texto sitúa los precios en UF, reflejados en pesos mediante una referencia mensual, sin aportar la UF base o fecha de conversión de los montos pegados. No se extrapolan esas condiciones al arriendo temporal ni a Panorama.

Esto identifica un tercer producto comercial posible, distinto de cupos de corredores y proyectos de inmobiliarias: **paquete por inmueble y vigencia para propietarios particulares**. Sus precios, gratuidad eventual y republicaciones propias están por definir. Detalles: [PUBLICACIONES_INDIVIDUALES_REFERENCIA.md](PUBLICACIONES_INDIVIDUALES_REFERENCIA.md).

## 19. Formularios de alta y baja del programa de corredores

El usuario aportó el contenido de ambos formularios del competidor. El alta comunica perfil público con nombre, antigüedad en sus portales, rango de venta, evaluación favorable y cantidad de inmuebles en venta, con filtros de zona y precio. Indica incorporación en hasta cinco días hábiles y cartera con ventas para aparecer. Pide nombre del solicitante, correo de captaciones, corredora y teléfono.

La baja comunica retiro del directorio y fin de captaciones para propietarios vendedores, **efectivos al final del mes**. Pide corredora, correos de publicación/captación, identificación y contacto de quien solicita y motivo de salida. No se confundió esa participación con una suscripción pagada de avisos.

Para Panorama se propone alta revisada y verificable, identificación clara de campos públicos, antigüedad propia, métricas sustentadas, pausa/baja desde el panel y cese inmediato de nuevas invitaciones como decisión a implementar. No se heredaron evaluaciones, políticas de privacidad, plazos de revisión ni cuentas de Google. El correo personal visible en las copias se omitió por ser un dato de sesión ajeno al diseño del programa.

La especificación detallada del programa incorpora campos, diferencias de alcance venta/arriendo y decisiones propuestas en [PROGRAMA_CONECTA_CORREDOR.md](PROGRAMA_CONECTA_CORREDOR.md), secciones 13 a 15. Los formularios son referencias documentales; no se enviaron datos ni se realizaron altas o bajas externas.

## 20. Actualización de implementación: backend PHP

Las descripciones anteriores del estado de implementación corresponden al prototipo frontend y antecedentes recogidos antes de esta fase. Se añadió una API independiente en PHP 8.3 con MySQL/MariaDB, migraciones, sesiones, roles y separación de organizaciones, propiedades con cupos transaccionales, imágenes, favoritos, CRM, agenda, proyectos/unidades, programa propio de captación, reportes y auditoría.

El programa de corredores tiene directorio con cobertura declarada, selección explícita de hasta tres corredores, aceptación/rechazo, contacto visible después de aceptar, gestión del propietario por token privado y baja inmediata. Son decisiones propias de esta implementación: no se copian la baja a fin de mes, evaluaciones históricas ni verificación del competidor.

Hay 59 rutas y pruebas de integración locales en SQLite y MariaDB. La interfaz Vue continúa usando localStorage; no hay hosting productivo, pago, facturación ni notificaciones de captación implementadas. Recuperación de contraseña tiene API/cola cifrada, pero requiere activar transporte y conectar pantalla frontend antes de ofrecerse. Los planes siguen sin precio propio ni contratación.

Guías vigentes: [Backend](../backend/README.md), [API](API_PHP.md), [Despliegue](DESPLIEGUE_PHP.md). El resultado no sustituye las decisiones comerciales, datos de la empresa ni revisión final de documentos de operación.
