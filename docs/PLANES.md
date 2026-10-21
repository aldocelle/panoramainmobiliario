# Planes mensuales y condiciones — propuesta de producto

## Oferta propia en preparación

Inicio: 5 publicaciones activas. Profesional: 30. Equipo: 100. Corporativo: capacidad por definir. Tarifas de Panorama pendientes, configurables en `src/data/plans.js`; no se copian las tarifas del competidor. `monthlyUf: null` significa precio no definido, no gratuidad. El cálculo neto/impuesto/total recibe una tasa explícita; su uso comercial requiere validar impuestos y redondeo con el sistema de facturación.

Cada propiedad con estado Publicada ocupa un cupo simultáneo. Editar no consume otro cupo. Borrador, Pausada, Vendida y Arrendada no cuentan. Se comprueba capacidad tanto en el asistente de publicación como al cambiar el estado desde la ficha. El cambio a menor capacidad se bloquea si los avisos activos no caben; nunca se borran ni se despublican de forma automática.

La selección de capacidad en la demostración es local y se identifica como simulación. No contrata un plan, no procesa pagos, no renueva ni genera obligaciones de cobro. Estos límites frontend se pueden alterar desde el navegador; requieren verificación transaccional en backend antes de operar.

## Contratación real pendiente

Catálogo comercial versionado; precio neto e impuestos aplicables; total; vigencia y renovación explícitas; método de pago; autorización de cobro; comprobantes; cancelación; cambios de plan con fecha efectiva; política de devolución; fallos de pago y conservación/exportación de datos. Persistir aceptación de condiciones definitivas por versión, usuario y fecha en servidor. No solicitar aceptación de borradores.

Cupos: usar transacciones para evitar que dos publicaciones simultáneas superen el límite. El servidor debe validar titularidad, estado de suscripción y cupos; verificar webhooks autenticados y procesarlos con idempotencia. Nunca activar un plan basándose solamente en una redirección de pago o un valor de localStorage.

## Referencias de la conversación

El usuario proporcionó la tabla de Portalinmobiliario.com con planes Plata mensuales en UF sin IVA y el enlace https://www.portalinmobiliario.com/pi/home/info/condiciones#item1 . El 21 de octubre de 2026, la recuperación directa de esa página devolvió 403. Las búsquedas devolvieron textos de otros dominios: no se usaron como evidencia de las condiciones chilenas ni se copiaron.

Tras recibirse el archivo completo de condiciones aportado por el usuario, se preparó un documento propio de 23 secciones en `docs/TERMINOS_Y_CONDICIONES.md`, compartido con la pantalla Condiciones. Ver `docs/ADAPTACION_CONDICIONES.md` para las decisiones de adaptación. No es un contrato vigente ni afirma cumplir todos los requisitos legales. Falta identificar la entidad operadora, canales de soporte, políticas comerciales y privacidad definitivas, además de revisión jurídica antes del lanzamiento comercial.

## Separación de segmentos

Planes y cupos distingue **Corredores y corredoras** (cupos individuales) de **Inmobiliarias y proyectos** (modelo propuesto por proyecto, zona y exposición). Las matrices y zonas transcritas del competidor se consultan únicamente en la pestaña de inmobiliarias. Ver `docs/INMOBILIARIAS.md`.
