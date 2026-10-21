# Contrato de API PHP

Base `/api/v1`. Todas las escrituras aceptan un objeto JSON y `Content-Type: application/json`; incluso DELETE/logout sin campos usan `{}`. Las respuestas son JSON salvo CSV e imágenes. Cookie `panorama_session` HttpOnly; recuperar `csrfToken` por login o sesión y enviarlo en `X-CSRF-Token` en cada escritura autenticada. Las rutas públicas funcionan sin sesión; si se envía una cookie válida, también se exige CSRF en escrituras.

## Rutas implementadas

| Método | Ruta | Acceso y propósito |
| --- | --- | --- |
| GET | `/health` | Público |
| POST | `/auth/recovery` | Público |
| POST | `/auth/reset` | Público |
| GET | `/favorites` | Sesión y permisos por rol |
| POST | `/favorites/{id}` | Sesión y permisos por rol |
| DELETE | `/favorites/{id}` | Sesión y permisos por rol |
| POST | `/media` | Sesión y permisos por rol |
| GET | `/media/{id}` | Sesión y permisos por rol |
| DELETE | `/media/{id}` | Sesión y permisos por rol |
| GET | `/public/media/{id}` | Público |
| POST | `/auth/register` | Público |
| POST | `/auth/login` | Público |
| POST | `/auth/logout` | Sesión y permisos por rol |
| GET | `/auth/session` | Sesión y permisos por rol |
| PUT | `/auth/password` | Sesión y permisos por rol |
| POST | `/auth/organization` | Sesión y permisos por rol |
| GET | `/workspace` | Sesión y permisos por rol |
| PUT | `/workspace` | Sesión y permisos por rol |
| GET | `/workspace/subscription` | Sesión y permisos por rol |
| GET | `/workspace/members` | Sesión y permisos por rol |
| POST | `/workspace/invitations` | Sesión y permisos por rol |
| POST | `/workspace/invitations/accept` | Sesión y permisos por rol |
| PUT | `/workspace/members/{id}` | Sesión y permisos por rol |
| DELETE | `/workspace/members/{id}` | Sesión y permisos por rol |
| GET | `/workspace/dashboard` | Sesión y permisos por rol |
| GET | `/workspace/export` | Sesión y permisos por rol |
| GET | `/workspace/reports/properties.csv` | Sesión y permisos por rol |
| GET | `/workspace/audit` | Sesión y permisos por rol |
| GET | `/properties` | Sesión y permisos por rol |
| POST | `/properties` | Sesión y permisos por rol |
| GET | `/properties/{id}` | Sesión y permisos por rol |
| PUT | `/properties/{id}` | Sesión y permisos por rol |
| DELETE | `/properties/{id}` | Sesión y permisos por rol |
| GET | `/leads` | Sesión y permisos por rol |
| PUT | `/leads/{id}` | Sesión y permisos por rol |
| DELETE | `/leads/{id}` | Sesión y permisos por rol |
| GET | `/visits` | Sesión y permisos por rol |
| POST | `/visits` | Sesión y permisos por rol |
| PUT | `/visits/{id}` | Sesión y permisos por rol |
| DELETE | `/visits/{id}` | Sesión y permisos por rol |
| GET | `/projects` | Sesión y permisos por rol |
| POST | `/projects` | Sesión y permisos por rol |
| PUT | `/projects/{id}` | Sesión y permisos por rol |
| GET | `/projects/{id}/units` | Sesión y permisos por rol |
| POST | `/projects/{id}/units` | Sesión y permisos por rol |
| PUT | `/projects/{id}/units/{unit}` | Sesión y permisos por rol |
| PUT | `/broker-program/profile` | Sesión y permisos por rol |
| DELETE | `/broker-program/profile` | Sesión y permisos por rol |
| GET | `/broker-program/requests` | Sesión y permisos por rol |
| PUT | `/broker-program/requests/{id}` | Sesión y permisos por rol |
| GET | `/public/properties` | Público |
| GET | `/public/properties/{id}` | Público |
| POST | `/public/properties/{id}/inquiries` | Público |
| GET | `/public/brokers` | Público |
| POST | `/public/owner-requests` | Público |
| GET | `/public/owner-requests/{id}` | Público |
| DELETE | `/public/owner-requests/{id}` | Público |
| GET | `/public/config` | Público |
| GET | `/public/plans` | Público |

## Reglas de uso

- `POST /auth/register`: `name`, `email`, `password`, `organizationName`, `kind` opcional (`broker`/`developer`). Cerrado por defecto. `POST /auth/login`: `email`, `password`. Contraseñas de 12 a 72 bytes, conservando espacios.
- `POST /auth/organization`: `organizationId`; exige membresía activa y emite nueva sesión/CSRF. `PUT /auth/password`: `currentPassword`, `password`; revoca todas las sesiones y crea la actual.
- Lecturas de cartera y CRM: todos los roles activos. Escrituras: owner/admin/agent. Archivo de propiedades, anonimización de contactos, marca, exportación e inscripción/baja del programa: owner/admin. Invitaciones y cambios de miembros: owner. Un owner no puede degradarse ni ser eliminado por estas rutas.
- Invitación: `email`, `role` (admin/agent/viewer). Devuelve token una sola vez; compartir por canal seguro. Aceptar con sesión del destinatario y `{ "token": "..." }`. Vence a las 48 horas. No hay correo automático.
- Listados: `page` desde 1 y `limit` de 1 a 100. Propiedades incluyen `total`; otros listados pueden incluir solo `items/page/limit`. No todos los recursos usan los mismos nombres de campos: propiedades usan camelCase, CRM/proyectos exponen campos snake_case del contrato actual.
- Propiedades: filtros `q`, `region`, `commune`, `operation`, `type` y `status` solo en cartera privada. Región como texto `01`..`16`. Comunas son texto libre sin catálogo oficial validado. No hay conversión CLP/UF ni comparación entre monedas.
- `PUT` de propiedades reemplaza campos editables completos y exige `version`. DELETE exige `{ "version": 1 }` y archiva. Cambios desactualizados devuelven 409. Estados `Borrador`, `Publicada`, `Pausada`, `Vendida`, `Arrendada`; solo Publicada de suscripción activa/piloto aparece públicamente.
- Precio UF: hasta dos decimales. CLP: entero. Internamente se almacena valor escalado a centésimas, nunca se convierte con una UF inventada. El precio de una propiedad es distinto del precio comercial de un plan.
- `POST /media`: `{ "contentBase64": "base64 puro sin prefijo data:" }`. Hasta 4 MiB, 4000 píxeles por lado y 8 megapíxeles. Devuelve `id/url/mime/bytes/width/height`; la URL puede usarse en `images`. Sin aviso publicado vinculado, la ruta pública devuelve 404. No se permite usar archivos internos ajenos ni borrar imágenes en uso. Hasta 1000 archivos por organización.
- Consultas: `name`, `email`, `phone` opcional, `note`, `consent:true`, `consentVersion` vigente; `website` opcional vacío como honeypot. La versión se obtiene en `/public/config`. `notificationSent:false` significa que se guardó el interesado sin envío de correo.
- Editar lead: `stage`, `note`, `version`. Etapas: Nuevo, Contactado, Visita agendada, En negociación, Cerrado. DELETE anonimiza contacto y notas/cancela visitas pendientes; no equivale a borrar todos los datos de esa persona en otros recursos.
- Visitas: `propertyId`, `leadId`, `agentId`, `startsAt`, `endsAt`, `note` opcional. Fechas ISO 8601 con zona explícita; futuras, hasta ocho horas. PUT agrega `version` y `status` (scheduled/cancelled/completed); DELETE cancela con versión. Respuestas usan epoch UTC para horarios.
- Perfil de captación: `regions`, `communes`, `operations`, `types`, `presentation`, `contactEmail`. Filtros del directorio: ver validador del servicio. Cobertura declarada y `identityVerified:false`; no hay puntaje inventado. La baja es inmediata en esta implementación propia.
- Captación pública: `name`, `email`, `phone`, `region`, `commune`, `operation`, `type`, `note`, `brokerIds` (1 a 3), consentimiento/version. `managementToken` se entrega una vez; GET/DELETE de gestión requieren `X-Request-Token`. No colocarlo en URL. Inbox oculta contacto hasta aceptación; decisión `{ "decision": "accepted" }` o `rejected`.
- Proyectos/unidades requieren organización developer. Código de unidad único por proyecto; versiones para edición. No hay precios comerciales por zona ni contratación de paquetes del competidor.
- Auditoría no contiene textos de consultas ni contraseñas. CSV escapa fórmulas; JSON exporta cartera de una organización, sin hashes/sesiones. Estas descargas contienen datos de negocio y deben guardarse con acceso restringido.

## Ejemplo: crear propiedad

```json
{
  "title": "Casa en venta",
  "type": "Casa",
  "operation": "Venta",
  "currency": "UF",
  "price": 3500.25,
  "region": "05",
  "commune": "Viña del Mar",
  "sector": "Centro",
  "privateAddress": "Dirección solo para la corredora",
  "area": 80,
  "beds": 2,
  "baths": 1,
  "description": "Descripción del inmueble",
  "images": [],
  "status": "Borrador"
}
```

## Errores y límites

`{ "error": { "code": "PUBLICATION_LIMIT", "message": "...", "details": [], "requestId": "UUID" } }`. `details` es opcional. 400 JSON/token inválido, 401 sesión ausente, 403 permisos/CSRF/origen, 404 recurso no accesible, 409 conflicto/cupo/versión, 413 carga grande, 415 formato, 422 validación, 429 límite, 503 configuración/servicio no disponible. Los errores internos no muestran SQL ni stack.

Se aplican contadores persistentes por IP (identificador HMAC), grupos de autenticación, escrituras públicas y carga de imágenes. Un 429 incluye Retry-After conservador. No reemplaza protección DDoS del proveedor. No registrar cuerpos ni tokens en herramientas de monitoreo.
