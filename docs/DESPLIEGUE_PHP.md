# Despliegue PHP y operación

## Arquitectura de instalación

Mantén `backend/src`, `bin`, `database`, `var`, `.env` y las pruebas fuera de la raíz web. Configura el document root del dominio API como `backend/public`. No publiques la raíz del repositorio. El `.htaccess` de `backend/` deniega acceso y el de `public/` habilita el controlador; son defensa adicional para Apache, no sustituyen una raíz web correcta. Nginx no interpreta esos archivos: debe servir exclusivamente `public/index.php`, sin rutas a archivos privados. No usar `php -S` en producción.

En Hostinger, utiliza un plan con PHP 8.3+, MySQL/MariaDB, extensiones necesarias, acceso SSH/CLI y tareas cron. La configuración y disponibilidad concretas del plan deben comprobarse en la cuenta antes de instalar. No se ha desplegado ni conectado ninguna cuenta de hosting.

1. Copia el código a una carpeta privada y configura la raíz web. Conserva `var/` entre versiones.
2. Crea base y usuario de aplicación con acceso limitado a esa base. Aplica migraciones con credenciales de despliegue que tengan DDL; utiliza credenciales sin DDL para servir tráfico si el proveedor permite separar usuarios.
3. Crea `.env` de producción con permisos `0600` y una clave generada por `php bin/key.php`; nunca la añadas a Git.
4. Configura HTTPS y `APP_ENV=production`, `COOKIE_SECURE=true`, `DB_DRIVER=mysql`, `DB_*`, `ALLOWED_ORIGINS=https://tu-dominio.cl`, `API_PREFIX=/api/v1`. Escribe orígenes exactos, sin barra final ni comodines.
5. Asegura escritura solo donde se requiere: `var/media` para PHP; código sin permisos de escritura del proceso web. Ajusta límites PHP: `memory_limit=256M`, `post_max_size=8M`; la aplicación limita imágenes originales a 4 MiB y cuerpos ordinarios a 128 KiB. No registra el contenido de cuerpos de solicitud.
6. Realiza respaldo, detén escrituras y ejecuta `php bin/migrate.php`. No hay migraciones durante peticiones. MySQL tiene DDL con commit implícito: ante fallo parcial, revisa/restaura antes de reintentar; no se ofrece rollback automático.
7. Crea la primera cuenta por stdin con `bin/create-account.php`; elimina el archivo de credenciales. Mantén `ALLOW_PUBLIC_REGISTRATION=false` hasta contar con control de altas.
8. Publica el aviso de privacidad y establece su versión en `PRIVACY_NOTICE_VERSION` para habilitar consultas/captaciones. Vacío desactiva esos formularios en API. Este ajuste no publica ni valida el documento legal.
9. Comprueba `/api/v1/health`, login, permisos cruzados entre dos organizaciones, imágenes y una publicación de prueba antes de abrir tráfico.

La API utiliza REMOTE_ADDR; no confía en X-Forwarded-For arbitrario. Si se coloca tras proxy, configura la IP de cliente en el servidor desde proxies de confianza y comprueba el rate limiting. Alarma externa para fallos y disponibilidad; logs de aplicación solo con request ID y tipo de error. Restringe acceso a logs del servidor y desactiva `display_errors` en PHP.

## Administración de capacidad

`php backend/bin/set-capacity.php < cambio.json` con:

```json
{"organizationId":"UUID de la organización","limit":30,"status":"active","reason":"Ajuste administrativo autorizado"}
```

Estados: `pilot`, `active`, `suspended`, `expired`. El comando bloquea la organización, exige pausar avisos antes de bajar de su ocupación, registra antes/después y motivo en `administrative_changes` y deja auditoría. No procesa dinero ni registra una compra. Restringe SSH y estos comandos a operadores autorizados.

## Correo y recuperación

`MAIL_TRANSPORT=disabled` por defecto: recuperación devuelve 503 explícito. `database` es un transporte de pruebas y está bloqueado en producción. Para `native`, configura `mail()`/MTA del proveedor, `MAIL_FROM` como dirección autorizada y `WEB_BASE_URL` como URL HTTPS del frontend. Prueba entrega real y autenticación del dominio en tu proveedor antes de activarlo.

El frontend debe implementar `/#reset-password?token=...` y enviar `POST /auth/reset`; la vista actual todavía no implementa ese formulario. No actives recuperación para clientes hasta conectar esa vista. El enlace usa fragmento para evitar token en logs HTTP; no incluir herramientas de seguimiento en esa pantalla.

La solicitud no revela existencia de la cuenta. Tokens de 30 minutos, almacenados como hash; cola cifrada con sodium y APP_KEY. Una recuperación exitosa revoca todas las sesiones. No cambiar APP_KEY sin planificar invalidación de colas y contadores; guardar la clave en el respaldo seguro.

Cron cada minuto, con ruta PHP absoluta del hosting:

```cron
* * * * * cd /ruta/privada/backend && /ruta/php bin/send-mail.php >> /ruta/privada/mail-worker.log 2>&1
15 * * * * cd /ruta/privada/backend && /ruta/php bin/cleanup.php >> /ruta/privada/cleanup.log 2>&1
```

La cola procesa hasta 50 mensajes por ejecución, reintenta hasta cinco veces y recupera trabajos bloqueados tras diez minutos. `mail()` confirma aceptación por transporte, no recepción. Entrega al menos una vez: un corte después del envío puede duplicarlo. Los mensajes entregados pierden inmediatamente el payload cifrado; revisar y purgar fallidos según política de retención. La limpieza elimina sesiones, tokens e invitaciones vencidas y registros entregados antiguos. No borra automáticamente cartera ni solicitudes de propietarios. Los correos de captación/consultas e invitaciones no están conectados a esta cola.

## Respaldo y restauración

- Producción: respaldar MySQL/MariaDB de forma consistente con la herramienta del proveedor o `mysqldump`/`mariadb-dump --single-transaction`, sin contraseña en línea de comandos. Usar archivo de credenciales privado.
- Respaldar también `var/media`, configuración y APP_KEY cifradas; la exportación JSON del corredor no sustituye el respaldo de base de datos.
- Cifrar copias fuera de la raíz web, limitar accesos y definir retención. Probar restauración en un entorno aislado antes de operar.
- Desarrollo SQLite: `php backend/bin/backup.php /ruta/privada/copia.sqlite` utiliza `VACUUM INTO`; no sobreescribe un archivo existente. Copiar imágenes por separado.
- Para revertir código, verificar compatibilidad del esquema. Si hay migración incompatible, detener escrituras y restaurar copia coordinada de datos e imágenes. No ejecutar comandos destructivos sobre producción desde las pruebas.

## Estado comprobado

Verificación local: PHP 8.3, SQLite y MariaDB 10.11; 38 pruebas de integración por motor, prueba MySQL de dos procesos, prueba HTTP y cliente JavaScript. PHP 8.4 figura en CI; no se afirma ejecución local en esa versión. No hay prueba de carga, despliegue externo ni validación de correo entregado. El prototipo frontend sigue aislado en localStorage hasta completar su integración.
