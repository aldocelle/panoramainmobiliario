#!/usr/bin/env bash
# Arma build/panorama/, el contenido exacto de la carpeta /panorama del hosting:
#   index.html, assets/, favicon.png  frontend compilado
#   api/                              punto de entrada PHP de la API
#   app/                              backend privado (código, .env, imágenes), denegado por .htaccess
# y build/sql/install.sql para importar el esquema en phpMyAdmin si el hosting no tiene SSH.
# Uso: deploy/build.sh [ruta/al/production.env]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/build/panorama"
SQL="$ROOT/build/sql"
ENV_FILE="${1:-$ROOT/deploy/production.env}"

fail() { echo "ERROR: $*" >&2; exit 1; }

# La configuración se valida antes de compilar, para no generar un paquete inservible.
[ -f "$ENV_FILE" ] || fail "falta $ENV_FILE (copia deploy/production.env.example y complétalo)."
get() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -1; }
[ "$(get APP_ENV)" = production ] || fail "APP_ENV debe ser production."
[[ "$(get APP_KEY)" =~ ^[0-9a-f]{64}$ ]] || fail "APP_KEY debe tener 64 caracteres hexadecimales (php backend/bin/key.php)."
[ "$(get DB_DRIVER)" = mysql ] || fail "DB_DRIVER debe ser mysql."
for k in DB_DATABASE DB_USERNAME; do [ -n "$(get $k)" ] || fail "$k está vacío."; done
# Si DB_PASSWORD está vacío se pide aquí: queda solo en el paquete (app/.env), no en deploy/production.env.
DB_PASS="$(get DB_PASSWORD)"
if [ -z "$DB_PASS" ]; then
  [ -t 0 ] || fail "DB_PASSWORD está vacío y no hay terminal para pedirlo."
  read -r -s -p "Contraseña de la base $(get DB_DATABASE): " DB_PASS; echo
  [ -n "$DB_PASS" ] || fail "la contraseña de la base no puede estar vacía."
fi
[[ "$(get ALLOWED_ORIGINS)" =~ ^https://[^/,]+(,https://[^/,]+)*$ ]] || fail "ALLOWED_ORIGINS debe ser https://dominio (o varios separados por coma), sin ruta ni barra final."
[[ "$(get API_PREFIX)" =~ ^(/[A-Za-z0-9_-]+)*/api/v1$ ]] || fail "API_PREFIX debe terminar en /api/v1 (p. ej. /panorama/api/v1)."
[ "$(get COOKIE_SECURE)" = true ] || fail "COOKIE_SECURE debe ser true (HTTPS)."

echo "› Pruebas"
(cd "$ROOT" && npm test >/dev/null && php backend/tests/run.php >/dev/null) || fail "hay pruebas fallando; no se arma el paquete."

echo "› Frontend"
(cd "$ROOT" && npm run build >/dev/null)
# Defensa: la contraseña de la cuenta de prueba local nunca debe quedar en el frontend publicado.
for key in VITE_DEMO_PASSWORD VITE_SUPERADMIN_PASSWORD; do
  DEMO_PASS="$(sed -n "s/^$key=//p" "$ROOT/.env.development.local" 2>/dev/null || true)"
  if [ -n "$DEMO_PASS" ] && grep -rqF "$DEMO_PASS" "$ROOT/dist"; then fail "el frontend compilado contiene una contraseña de prueba ($key)."; fi
done
unset DEMO_PASS

echo "› Paquete en build/panorama"
rm -rf "$ROOT/build"
mkdir -p "$OUT/api" "$OUT/app/var/media" "$SQL"
cp -R "$ROOT/dist/." "$OUT/"
cp -R "$ROOT/backend/src" "$ROOT/backend/bin" "$ROOT/backend/database" "$ROOT/backend/bootstrap.php" "$OUT/app/"
cp "$ROOT/deploy/templates/htaccess-root" "$OUT/.htaccess"
cp "$ROOT/deploy/templates/htaccess-deny" "$OUT/app/.htaccess"
cp "$ROOT/deploy/templates/htaccess-deny" "$OUT/app/var/.htaccess"
# Fotos exportadas con deploy/portfolio-export.php (cartera ya optimizada): viajan en la subida.
if compgen -G "$ROOT/export/media/*.webp" >/dev/null; then
  cp "$ROOT"/export/media/*.webp "$OUT/app/var/media/"
  echo "  + $(ls "$ROOT"/export/media/*.webp | wc -l | tr -d ' ') fotos de export/media"
fi
cp "$ROOT/backend/public/.htaccess" "$OUT/api/.htaccess"
sed 's#dirname(__DIR__) . "/bootstrap.php"#dirname(__DIR__) . "/app/bootstrap.php"#' "$ROOT/backend/public/index.php" > "$OUT/api/index.php"
grep -q '"/app/bootstrap.php"' "$OUT/api/index.php" || fail "no se pudo ajustar api/index.php."
umask 077
grep -v '^DB_PASSWORD=' "$ENV_FILE" > "$OUT/app/.env"
printf 'DB_PASSWORD=%s\n' "$DB_PASS" >> "$OUT/app/.env"
unset DB_PASS

# Esquema completo + registro en schema_migrations, para que bin/migrate.php no lo repita después.
{
  echo "-- Panorama Inmobiliario: esquema inicial. Importar UNA vez en una base vacía."
  echo "CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(160) PRIMARY KEY, applied_at VARCHAR(32) NOT NULL);"
  for f in "$ROOT"/backend/database/migrations/*.sql; do
    echo; echo "-- $(basename "$f")"
    cat "$f"; echo
    echo "INSERT INTO schema_migrations VALUES('$(basename "$f")','$(date -u +%Y-%m-%dT%H:%M:%SZ)');"
  done
} > "$SQL/install.sql"

echo
echo "Listo:"
echo "  build/panorama/      → contenido de /panorama ($(find "$OUT" -type f | wc -l | tr -d ' ') archivos)"
echo "  build/sql/install.sql → esquema para phpMyAdmin (solo si no hay SSH)"
echo "No subas build/sql al servidor."
