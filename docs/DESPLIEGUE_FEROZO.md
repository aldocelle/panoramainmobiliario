# Despliegue en Ferozo (carpeta /panorama)

Instalación en un hosting compartido que ya tiene otros sitios. Panorama usa **solo la carpeta `/panorama`** y una base MySQL propia. Los scripts no borran archivos del servidor ni tocan nada fuera de esa carpeta.

## Estructura en el servidor

```
/panorama/
  index.html, assets/, favicon.png   frontend
  .htaccess                          sin listado de carpetas, oculta archivos con punto
  api/index.php + .htaccess          entrada de la API
  app/                               código, .env e imágenes; denegado por .htaccess
    var/media/                       fotos subidas (debe conservarse entre versiones)
```

`app/` queda dentro de la carpeta web y se protege con `.htaccess` (Apache). Si el panel permite que la raíz del sitio apunte a otra carpeta, es preferible dejar `app/` fuera de la raíz pública.

## Antes de empezar

1. En el panel de Ferozo, comprueba que el dominio usa **PHP 8.3 o superior** con las extensiones `pdo_mysql`, `mbstring`, `sodium`, `fileinfo` y `gd` (con WebP).
2. Cambia las contraseñas de FTP y de la base si alguna vez se compartieron por chat o correo.
3. Completa `deploy/production.env` (no se sube a git). Ya trae nombre de base, usuario, clave de la aplicación y prefijos. Revisa:
   - `DB_PASSWORD`: puedes dejarla vacía; `build.sh` la pide en la terminal y la escribe solo en el paquete.
   - `DB_USERNAME`: en Ferozo suele coincidir con el nombre de la base; confírmalo en el panel.
   - `ALLOWED_ORIGINS`: dominio exacto con `https://`, sin ruta.
   - `API_PREFIX`: `/panorama/api/v1` si el sitio se ve en `dominio.cl/panorama`; `/api/v1` si un subdominio tiene `/panorama` como raíz.

`APP_KEY` cifra datos internos: guarda una copia segura del archivo y no la cambies después de instalar.

## Instalación

```bash
deploy/build.sh                       # valida configuración, corre pruebas y arma build/panorama + build/sql
```

**Base de datos (una sola vez, base vacía):**

- Con SSH: tras subir los archivos, `cd /panorama/app && php bin/migrate.php`, luego `php bin/create-platform-admin.php < admin.json` (superadmin) y `php bin/create-account.php < cuenta.json`.
- Sin SSH (phpMyAdmin):
  1. Importa `build/sql/install.sql` en la base.
  2. Ejecuta `deploy/superadmin-sql.sh` e importa `build/sql/superadmin.sql` (acceso a `/panorama/#superadmin`).
  3. Ejecuta `deploy/first-account-sql.sh` en tu equipo (pide nombre, correo, corredora y contraseña) e importa `build/sql/first-account.sql`. Bórralo después; contiene el hash de la contraseña.

**Subida por FTPS:**

```bash
FTP_HOST=a0031369.ferozo.com FTP_USER=tu-usuario-ftp deploy/upload.sh
```

El script pide la contraseña en la terminal, muestra el contenido actual de `/panorama` y la lista de archivos a subir, y solo continúa si escribes `SUBIR`. Si tu cuenta FTP abre directamente dentro de la carpeta panorama, añade `FTP_REMOTE_DIR=/`.

## Comprobación

1. `https://tu-dominio.cl/panorama/api/v1/health` responde `{"status":"ok","database":"ok"}`.
2. `https://tu-dominio.cl/panorama/app/.env` y `/panorama/app/bootstrap.php` responden 403.
3. Ingresa con la cuenta creada, crea un borrador, sube una foto y publícalo; debe aparecer en el portal.
4. Las consultas del portal quedan deshabilitadas mientras `PRIVACY_NOTICE_VERSION` esté vacío. Complétalo solo cuando publiques el aviso de privacidad real.

## Actualizaciones

Vuelve a ejecutar `deploy/build.sh` y `deploy/upload.sh`. Los archivos nuevos o cambiados se sobrescriben; las fotos de `app/var/media` no se tocan. Si hay migraciones nuevas en `backend/database/migrations/`, aplícalas antes de subir el código: con SSH `php bin/migrate.php`; sin SSH importa ese archivo en phpMyAdmin y agrega su fila en `schema_migrations`.

## Imágenes

Toda foto se optimiza dos veces: en el navegador antes de subirla (lado mayor ≤ 2560 px, WebP) y en el servidor (orientación de cámara, lado mayor ≤ 1920 px, WebP calidad 78 y miniatura de 640 px). Las fotos públicas se sirven con caché de un año porque cada id es inmutable. Si se cambian esos tamaños o se migran fotos antiguas, ejecuta `php bin/optimize-media.php` en `app/` (con SSH): genera las miniaturas que falten y solo reemplaza una foto si la nueva pesa menos.

## Llevar una cartera local a producción

`php deploy/portfolio-export.php <id-org-local> <id-org-producción> /panorama/api/v1 <cupo>` genera `export/portfolio.sql` (propiedades, fotos y cupo, con el cambio registrado) y copia las fotos optimizadas a `export/media/`. Importa el SQL en phpMyAdmin y ejecuta `deploy/publish.sh --update` respondiendo `n` a reutilizar el paquete: `build.sh` incluye `export/media` en `app/var/media`. `export/` no se versiona.
