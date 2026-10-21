# API PHP de Panorama Inmobiliario

API JSON independiente del prototipo Vue. PHP 8.3+, MySQL 8/MariaDB 10.11 en producción y SQLite para desarrollo. PDO, consultas preparadas, migraciones SQL, sesiones opacas en cookie y separación por organización. Sin dependencias PHP de terceros; `composer.json` declara las extensiones y permite validar la plataforma. Autocargador en `bootstrap.php`.

## Funciones implementadas

- Cuentas, login/logout, cambio y recuperación de contraseña, sesiones revocables y cambio de organización.
- Roles `owner`, `admin`, `agent`, `viewer`; invitaciones con destinatario, vencimiento y token de un uso. Revocación inmediata del acceso de miembros.
- Propiedades, borradores, publicación, pausa, archivo lógico, versiones contra sobrescritura y búsqueda paginada. Cupos comprobados en transacciones con bloqueo de organización; suspender retira anuncios del portal público.
- Marca de la corredora; imágenes JPEG/PNG/WebP recodificadas a WebP fuera de la raíz pública; favoritos por persona.
- Consultas públicas con consentimiento versionado, interesados, notas y etapas, anonimización y agenda con conflictos por propiedad o profesional.
- Inmobiliarias: proyectos y unidades con códigos únicos, precios y disponibilidad, separados del corretaje. Administración privada; publicación pública de proyectos todavía pendiente.
- Programa propio de conexión con corredores: cobertura declarada, directorio, selección de hasta tres destinatarios, aceptación/rechazo, gestión con token privado y baja inmediata. La cobertura no se presenta como certificada ni como historial verificado.
- Resumen operativo, CSV protegido contra fórmulas, exportación JSON y auditoría por organización.
- Rate limiting persistente, CSRF, CORS de orígenes exactos, límites de carga, respuestas sin excepciones/SQL ni claves internas.

## Inicio local

Extensiones: `pdo`, `pdo_mysql`, `pdo_sqlite` (pruebas locales), `mbstring`, `sodium`, `fileinfo`, `gd` con WebP. La configuración no instala extensiones automáticamente.

Desde `backend/`:

```bash
cp .env.example .env
php bin/key.php
```

Pega la clave generada en `APP_KEY`. Para desarrollo, configura `DB_DRIVER=sqlite` y `DB_PATH=var/development.sqlite`. El registro público está cerrado por defecto. Crea cuentas administrativamente o habilítalo explícitamente solo en el entorno adecuado.

```bash
php bin/migrate.php
php bin/create-account.php < cuenta.json
php -S 127.0.0.1:8080 -t public router.php
```

Ejemplo de `cuenta.json` temporal, con permisos restringidos, fuera del repositorio:

```json
{"name":"Nombre del administrador","email":"administracion@example.com","password":"Usar una contraseña única de al menos 12 caracteres","organizationName":"Nombre corredora","kind":"broker"}
```

`kind` admite `broker` o `developer`. Elimina el archivo temporal después de usarlo. La cuenta inicial obtiene un cupo piloto de cinco avisos, sin contratación ni cobro. La salida administrativa identifica usuario y organización, sin devolver contraseña. `GET http://127.0.0.1:8080/api/v1/health` comprueba conexión a base de datos; no aplica migraciones.

## Integración con Vue

El frontend del repositorio **todavía usa datos ficticios y localStorage**. La API no cambia ese comportamiento por sí sola. Usa [el cliente JavaScript](client/panorama-api.js) para la migración de vistas, reemplazando las lecturas/escrituras locales por solicitudes autenticadas. No importes los datos ficticios como cartera real.

```js
import { PanoramaApi } from './backend/client/panorama-api.js'
const api = new PanoramaApi('http://localhost:8080/api/v1')
await api.login(email, password)
const cartera = await api.properties({ page: 1, limit: 20 })
```

Tras recargar la página, llama `session()` para recuperar el usuario y CSRF. El token se mantiene en memoria y la sesión en cookie HttpOnly. No guardes contraseñas ni cookies en localStorage. Usa el mismo dominio/sitio HTTPS en producción; `SameSite=Lax` no admite una integración entre sitios independientes. Los errores 409 de versiones requieren recargar el registro y revisar cambios; no repetir escrituras automáticamente.

Consulta [API](../docs/API_PHP.md) y [despliegue](../docs/DESPLIEGUE_PHP.md).

## Pruebas

Desde la raíz del repositorio:

```bash
php backend/tests/run.php
python3 backend/tests/http-smoke.py
node --test backend/client/panorama-api.test.js
```

La suite por defecto usa SQLite en memoria y una carpeta temporal de imágenes. Para MariaDB/MySQL utiliza **una base nueva y desechable**, con prefijo `panorama_test_`, configura las variables `DB_*` y ejecuta:

```bash
PANORAMA_TEST_MYSQL=true php backend/tests/run.php
php backend/tests/concurrency.php
```

La segunda prueba abre dos procesos independientes compitiendo por un cupo. Si usas un ejecutable PHP con opciones especiales, define `PANORAMA_PHP_COMMAND` con la ruta a un wrapper ejecutable. La suite no limpia bases previamente pobladas: crea una nueva por ejecución. El workflow prueba PHP 8.3/8.4 con SQLite y MariaDB; la ejecución remota depende de GitHub Actions.

## Límites actuales

Esta entrega proporciona una base de API probada localmente, **no un despliegue productivo terminado**. Pendientes: integración completa de pantallas Vue; verificación de email/MFA; automatización de entrega de invitaciones (hoy token manual); tarifas propias, pasarela, webhooks y facturación; correo de consultas; catálogo oficial de comunas y ciudades; mapas/geobúsqueda; importaciones masivas; métricas de exposición/clics y analítica histórica; moderación y verificación de identidad; publicación pública de proyectos; política operativa de retención de datos; pruebas de carga y observabilidad externa.

Los precios del competidor son antecedentes históricos documentados, no tarifas de Panorama. Los planes de API devuelven precios `null` y contratación deshabilitada. La capacidad solo cambia por comando administrativo auditado; no hay endpoint que permita autocontratar un plan.
