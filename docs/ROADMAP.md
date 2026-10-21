# Desarrollo posterior

## Base funcional entregada

Portal público filtrable, favoritos, fichas, consulta local, panel de resumen, cartera editable, asistente de tres pasos, seguimiento de contactos, agenda y respaldo JSON. Todo corresponde a una demostración de frontend.

## Antes de un piloto real

- API y base de datos relacional con migraciones y copias de seguridad.
- Inicio de sesión, recuperación de acceso, roles y aislamiento por corredora.
- Validación de servidor, autorización por registro y pruebas de acceso cruzado.
- Catálogo territorial validado; búsqueda por región y comuna con identificadores.
- Carga segura de fotografías, almacenamiento, límites y galería.
- Consentimiento, política de privacidad y reglas de conservación de contactos.
- Flujo de consultas con protección contra spam y confirmación de entrega.
- Agenda con zona horaria, duración, detección de solapamientos e invitaciones.
- Pruebas completas de navegador en escritorio y celular; auditoría de accesibilidad.

## Monetización posterior

Suscripción para herramientas del corredor, perfil/sitio profesional y destacados cuando exista tráfico medible. El prototipo ya incluye planes con cupos simulados y beneficios de marca/reporte local. La contratación real, pagos, facturación, renovaciones y permisos por plan en servidor siguen pendientes.

Los antecedentes y decisiones consolidados se mantienen en [ANTECEDENTES_PANORAMA_INMOBILIARIO.md](ANTECEDENTES_PANORAMA_INMOBILIARIO.md).

## Actualización: API PHP independiente

Implementados en `backend/`: cuentas, roles, sesiones, migraciones, persistencia multiempresa, cartera/cupos, imágenes, favoritos, interesados, agenda, proyectos/unidades, captación y auditoría. Probados con SQLite y MariaDB. El frontend local todavía no consume esta API.

Siguiente trabajo: integrar las vistas y formularios reales; verificación de cuentas; correo en hosting y pantalla de recuperación; facturación y pasarela; catálogo territorial; geobúsqueda; publicación pública de proyectos; moderación y métricas históricas. Documentación de instalación en `DESPLIEGUE_PHP.md` y contrato en `API_PHP.md`.
