# Conecta con un corredor — propuesta para Panorama Inmobiliario

**Estado:** especificación de producto y API PHP implementada; integración frontend pendiente.
**Fecha:** 21 de octubre de 2026.
**Nombre de trabajo propio:** Conecta con un corredor. No es una marca cuya disponibilidad se haya comprobado.

## 1. Antecedente aportado por el usuario

El usuario compartió una descripción de 14 preguntas y respuestas del programa de Portalinmobiliario.com denominado «Elige tu Corredor». El texto también usa «Encuentra tu Corredor» en una de sus respuestas; se conserva esa observación sin asumir que se trata de otro programa.

El contenido siguiente es una síntesis de la referencia aportada, no una comprobación de que sus condiciones sigan vigentes.

| Pregunta de la referencia | Antecedente comunicado |
| --- | --- |
| 1. Qué es | Conecta propietarios que quieren vender o arrendar con corredores |
| 2. Cómo funciona | El propietario completa datos personales y del inmueble, elige corredores y estos aceptan o rechazan la solicitud |
| 3. Inscripción del corredor | Requiere ser usuario del portal y completar un formulario de inscripción |
| 4. Salida del corredor | Se realiza mediante un formulario de desinscripción |
| 5. Costo | El programa se presenta como gratuito; la comisión del corredor corresponde a la operación inmobiliaria |
| 6. Comisión | Variable según cada corredor |
| 7. Beneficio para corredores | Captar propiedades para ampliar su cartera |
| 8. Beneficio para propietarios | Elegir profesionales con información de desempeño y facilitar la gestión |
| 9. Selección múltiple | El propietario puede elegir a varios corredores |
| 10. Plazo de contacto | La referencia no establece un plazo; depende del corredor |
| 11. Ausencia de respuesta | El propietario puede usar WhatsApp o teléfono incluidos en el correo recibido |
| 12. Tipos de propiedad | Casas y departamentos |
| 13. Sin profesionales disponibles | No hay coincidencia por zona o segmento de precio; se ofrece publicar directamente |
| 14. Evaluación | Encuesta de satisfacción enviada por correo |

El usuario aportó enlaces a formularios Google del competidor. No se utilizaron para inscribir, desinscribir ni transferir datos. Panorama debe implementar sus propios formularios y condiciones.

## 2. Objetivo de la propuesta propia

Agregar un canal de captación de propiedades al ecosistema de Panorama: una persona propietaria solicita ayuda para vender o arrendar, compara profesionales disponibles y decide con quién compartir su solicitud. El corredor obtiene oportunidades de nuevos encargos, diferenciadas de las consultas de compradores o arrendatarios por una propiedad ya publicada.

No debe confundirse este programa con un mandato de corretaje, una reserva, una tasación, una comisión aceptada o una garantía de venta. Cualquier encargo, exclusividad y honorario debe acordarse separadamente entre las partes con información suficiente.

## 3. Experiencia del propietario

1. Seleccionar venta o arriendo y tipo de inmueble. Alcance inicial propuesto: casas y departamentos; ampliar a otros tipos solo con cobertura profesional confirmada.
2. Indicar región, comuna, sector opcional y precio estimado o la alternativa «necesito orientación». No exigir ubicación exacta ni documentos sensibles para explorar profesionales.
3. Consultar perfiles que hayan declarado cobertura y estén recibiendo solicitudes. Mostrar operación, tipos de propiedad, zonas atendidas, idiomas si procede, presentación, honorarios informados y disponibilidad.
4. Elegir uno o varios corredores. Límite inicial propuesto: tres por solicitud para evitar difusión excesiva; es una decisión a validar, no una regla comercial vigente.
5. Revisar un resumen con el nombre de cada destinatario y qué datos recibirá, entregar contacto y confirmar la solicitud.
6. Ver el estado de cada invitación: pendiente, aceptada, rechazada, vencida o cancelada. Si un corredor acepta, coordinar directamente los siguientes pasos.
7. Cancelar solicitudes pendientes y pedir cese de contactos cuando corresponda. Las finalidades y el efecto sobre datos ya compartidos deben explicarse en la política propia.
8. Evaluar una interacción real mediante una encuesta vinculada a la solicitud, sin publicar automáticamente el texto libre.

## 4. Experiencia del corredor

- Inscribirse voluntariamente en el panel de Panorama, sin depender de formularios del competidor.
- Completar perfil profesional, identidad y representación cuando corresponda; distinguir datos declarados de datos efectivamente verificados.
- Configurar comunas, tipos de inmueble, operaciones y segmentos de precio que atiende, además de disponibilidad para recibir nuevos encargos.
- Pausar o reanudar participación y salir del programa desde el mismo panel. Pausar evita nuevas invitaciones, pero no borra acuerdos ya celebrados.
- Revisar oportunidades de captación en una bandeja independiente de los interesados en avisos publicados.
- Aceptar o rechazar sin que una recepción automática se interprete como encargo aceptado.
- Registrar siguiente acción y resultado: contacto, reunión, propuesta de servicio, encargo acordado o cierre sin encargo.
- Mantener honorarios y condiciones actualizados; ninguna comisión se da por aceptada por el solo envío de una solicitud.

## 5. Diferenciación propuesta

| Aspecto | Propuesta de Panorama | Estado |
| --- | --- | --- |
| Participación | Inscripción, pausa y retiro desde el panel | Pendiente |
| Contacto múltiple | Elección expresa y control de destinatarios | Pendiente |
| Seguimiento | Estado visible para propietario y corredor | Pendiente |
| Tiempo de atención | Objetivo configurable; explorar 24 horas hábiles para responder invitaciones, sin presentarlo como garantía | Por validar |
| Solicitudes sin respuesta | Vencimiento y opción de elegir otros profesionales; sin derivación automática no autorizada | Pendiente |
| Datos | Entrega limitada y trazable, según destinatarios informados | Pendiente |
| Reputación | Métricas verificables, metodología y posibilidad de revisión | Pendiente |
| Alternativa sin cobertura | Publicación directa o aviso de disponibilidad, según funciones habilitadas | Pendiente |

El objetivo de respuesta debe validarse con corredores y capacidad operativa antes de prometerlo. Si se habilita, el sistema deberá definir horas hábiles, feriados, recordatorios y tratamiento de ausencias.

## 6. Reglas de coincidencia y presentación

La coincidencia debe considerar comuna atendida, operación, tipo de inmueble, segmento de precio y disponibilidad declarada del corredor. La zona comercial de la matriz para inmobiliarias no debe reutilizarse como si fuera cobertura profesional de este programa.

El precio indicado describe el inmueble o encargo buscado; no se utiliza para inferir elegibilidad financiera del propietario. No se solicitarán características sensibles para ordenar personas o determinar acceso al servicio.

Si el propietario desconoce el valor, deberá poder solicitar orientación a profesionales que acepten ese tipo de consulta. No se inventarán candidatos para llenar resultados ni se atribuirá experiencia verificada a partir de una simple declaración.

Si se incorporan espacios patrocinados, deberán identificarse. El pago de una suscripción no debe presentarse como una certificación de calidad. La metodología de ordenamiento, criterios de verificación y datos usados para indicadores deben ser explicables.

## 7. Privacidad y tratamiento de datos

Antes de operar deben definirse datos mínimos, responsable de cada tratamiento, destinatarios, base habilitante, finalidades, conservación, mecanismos de derechos y proveedores externos. El propietario debe conocer a quién se enviará su solicitud antes de confirmarla.

Propuesta de minimización: mostrar a candidatos únicamente información inmobiliaria general para valorar cobertura; entregar contacto según el paso y las autorizaciones expresamente informados. No exponer datos de propietario en un directorio público, vender listas ni autorizar campañas ajenas a la solicitud.

Registrar selecciones, avisos presentados y eventos de entrega para poder resolver reclamos. La cancelación de una solicitud debe detener envíos futuros del programa; el tratamiento por un profesional que ya recibió datos requiere reglas claras, sin prometer que se puede borrar instantáneamente toda copia externa.

El acceso a contactos necesita permisos por organización y registros de auditoría. WhatsApp, correo y teléfono solo se incorporarán con canales y usos informados. No enviar invitaciones o mensajes reales desde una demostración sin infraestructura y autorización adecuadas.

## 8. Comisiones, cobros e ingresos posibles

Referencia externa: el programa aportado se presenta como gratuito y las comisiones dependen del corredor. No se ha comprobado ni adoptado una tarifa de programa para Panorama.

Propuesta de validación: acceso sin costo para el propietario, con participación profesional inicial a definir. Ninguna comisión inmobiliaria debe quedar implícitamente aceptada. Informar previamente importe o cálculo, momento de devengo, impuestos aplicables y cualquier exclusividad en el acuerdo separado.

Opciones comerciales de Panorama, todavía no decididas:

- Incluir herramientas de captación y seguimiento dentro del plan profesional.
- Cobrar por herramientas adicionales de organización, sin prometer una cantidad de encargos.
- Ofrecer perfiles patrocinados claramente identificados y con criterios de cobertura.
- Explorar pago por oportunidad solo después de definir qué es válida, duplicados, rechazo, consentimiento y controversias. No está habilitado.

La suscripción, una oportunidad recibida y una comisión por corretaje son conceptos separados. No se implementa comisión de éxito ni reparto de honorarios en esta propuesta.

## 9. Estados y modelo de información propuesto

| Entidad | Datos principales |
| --- | --- |
| Participación del corredor | Organización, profesional, estado, cobertura, operación, inmueble, segmento de precio, condiciones y fechas |
| Solicitud del propietario | Identificador, propietario, operación, ubicación general, datos del inmueble, precio u orientación, estado y fecha |
| Invitación | Solicitud, corredor elegido, pendiente/aceptada/rechazada/vencida/cancelada y fechas |
| Evento de contacto | Invitación, responsable, acción, resultado y siguiente paso |
| Registro de entrega | Destinatarios informados, datos compartidos, versión de aviso, base/habilitación y fecha |
| Evaluación | Invitación asociada, experiencia real, respuesta, moderación y revisión |

Los estados de contacto no prueban la existencia de un mandato. Las invitaciones deben procesarse con controles de concurrencia e idempotencia para evitar duplicados y entregas posteriores a una cancelación.

## 10. Indicadores útiles

- Solicitudes válidas recibidas por región, comuna, operación y tipo.
- Porcentaje de solicitudes con candidatos disponibles.
- Invitaciones aceptadas, rechazadas, vencidas y canceladas.
- Tiempo de respuesta a invitación y tiempo hasta primer contacto registrado, distinguiendo qué puede comprobar la plataforma.
- Conversión a reunión o encargo, señalando si el dato fue declarado o verificado.
- Satisfacción de interacciones reales y solicitudes de revisión de indicadores.
- Motivos de falta de coincidencia y duplicación, con minimización de datos.

No se deben mostrar porcentajes de éxito, estrellas o testimonios ficticios para un profesional sin historial.

## 11. Criterios de aceptación antes del piloto

- Publicar una solicitud no genera un aviso público ni un contrato de corretaje.
- Cada contacto recibido corresponde a una selección y aviso de entrega trazables.
- Un corredor pausado no recibe nuevas invitaciones.
- El rechazo, vencimiento o cancelación impide nuevos envíos de esa invitación.
- Una solicitud sin coincidencias ofrece una alternativa real o informa la ausencia sin inventar cobertura.
- Un propietario puede conocer y gestionar destinatarios y estados.
- Las comisiones no se fijan ni aceptan silenciosamente desde la plataforma.
- El acceso a solicitudes y datos está aislado entre organizaciones.
- Los indicadores tienen definición, datos de origen y posibilidad de revisión.
- Las comunicaciones son verificables y las políticas propias están publicadas antes de recibir datos reales.

## 12. Dependencias y alcance actual

Esta propuesta amplía la captación de propietarios planteada en el documento maestro. No se ha implementado un directorio de corredores, formularios reales, coincidencia, encuestas ni entrega de contactos. Requiere backend, autenticación, perfiles, permisos, catálogo territorial validado, notificaciones, política de datos, condiciones específicas y proceso de soporte/moderación.

Enlace al conjunto de antecedentes: [ANTECEDENTES_PANORAMA_INMOBILIARIO.md](ANTECEDENTES_PANORAMA_INMOBILIARIO.md).

## 13. Detalle adicional: formulario de inscripción del competidor

Texto aportado posteriormente por el usuario; no se envió ni completó el formulario.

El formulario comunica que, al inscribirse, la corredora autoriza aparecer en una página de captación de propietarios. Los atributos públicos descritos son:

| Dato público | Alcance que indica la referencia |
| --- | --- |
| Nombre | Nombre de la corredora |
| Antigüedad | Año desde que publica en Portalinmobiliario y Mercado Libre, con referencia «post 2015» |
| Rango de precios | Precios de propiedades en venta |
| Evaluación de atención | Solo corredoras con evaluación verde o verde claro |
| Cantidad de propiedades en venta | Con filtros por región, comuna y precio |

La inscripción anuncia publicación en un máximo de **cinco días hábiles** y exige contar con propiedades **en venta** en la cartera. Remite a la política de privacidad de Mercado Libre.

Campos solicitados:

- Nombre y apellido de quien responde (obligatorio).
- Correo para recibir captaciones (obligatorio).
- Nombre de la corredora (obligatorio).
- Teléfono de contacto (obligatorio), con ejemplo de formato `+569XXXXXXXX`.

Las referencias a cuenta de Google, cambio de cuenta y el correo mostrado como sesión no son campos ni requisitos de Panorama y no se incorporan al repositorio como datos personales.

**Distinción importante:** el plazo de cinco días hábiles es para aparecer en el directorio, no un plazo de respuesta del corredor al propietario. El formulario se concentra en cartera de venta, mientras la descripción general aportada también menciona arriendo. No se supone elegibilidad automática para ambos sin aclaración.

## 14. Detalle adicional: formulario de desinscripción del competidor

El texto aportado indica que la baja elimina a la corredora del directorio, detiene las captaciones de propietarios interesados en vender y se hace efectiva **al término del mes de la solicitud**.

| Campo | Obligatoriedad visible en el texto aportado |
| --- | --- |
| Nombre de corredora | Obligatorio |
| Correo con el que publican en Portalinmobiliario.com | Sin asterisco visible |
| Correo donde reciben captaciones | Obligatorio |
| Nombre y apellido de quien solicita la baja | Obligatorio |
| Correo de quien solicita la baja | Obligatorio |
| Teléfono, con ejemplo `+569XXXXXXXX` | Obligatorio |
| Motivo de la baja | Sin asterisco visible |

Se describe un formulario creado en Mercadolibre SRL mediante Google Forms. Esa identificación y sus enlaces de sesión o denuncia no pertenecen al producto propio. No se ejecutó ninguna inscripción o baja externa.

## 15. Decisiones de adaptación propuestas para Panorama

| Materia | Propuesta propia |
| --- | --- |
| Perfil público | Nombre comercial, presentación, zonas, operaciones, tipos, rango atendido y condiciones informadas |
| Antigüedad | Distinguir fecha real de incorporación a Panorama de experiencia profesional declarada; no importar antigüedad en otro portal como verificada |
| Evaluación | Medición propia con metodología y período; sin colores o calificaciones heredadas del competidor |
| Elegibilidad | Revisar cobertura, disponibilidad y autorización profesional; definir por separado venta y arriendo |
| Volumen de cartera | Calcular desde avisos activos propios; no inventar cantidad ni obligar a tener ventas si solo se ofrece arriendo |
| Alta | Revisión con estado pendiente/aprobado/rechazado y motivo; plazo operativo de Panorama aún por definir |
| Datos de inscripción | Nombre del solicitante autorizado, organización, correo profesional de captación y teléfono; validación de contacto y representación en servidor |
| Privacidad | Aviso propio con campos públicos y privados diferenciados; no usar el consentimiento ni la política de Mercado Libre |
| Pausa y baja | Propuesta de detener inmediatamente nuevas invitaciones desde el panel, con confirmación; no arrastrar automáticamente la demora hasta fin de mes |
| Motivo de salida | Opcional; no condiciona el retiro |
| Cuenta y plan | Salir del programa no cancela automáticamente la cuenta, el plan de publicaciones ni un mandato acordado con un propietario |
| Solicitudes anteriores | Mostrar su estado, detener nuevos envíos cuando corresponda y explicar tratamiento de datos ya entregados |

Estas decisiones siguen siendo una especificación. No existe inscripción, desinscripción o validación real del directorio en el prototipo actual.

## Actualización técnica

La API `backend/` implementa ahora alta/baja, cobertura declarada, directorio, captaciones a uno/tres corredores, aceptación/rechazo y gestión del propietario mediante token privado. El frontend del prototipo todavía no conecta estos flujos. La baja implementada es inmediata y el contacto se entrega tras aceptación; no hay emails automáticos, puntuación ni verificación de identidad. Ver `API_PHP.md`. Las condiciones propuestas en secciones anteriores son una especificación de producto; prevalece esta descripción al evaluar el código entregado.
