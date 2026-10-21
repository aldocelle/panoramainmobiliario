#!/usr/bin/env bash
# Genera build/sql/first-account.sql con la primera cuenta (usuario + corredora + cupo piloto),
# para importarla en phpMyAdmin cuando el hosting no tiene SSH.
# Usa el mismo código de la API sobre una base SQLite temporal; la contraseña se escribe aquí,
# en tu equipo, y el archivo solo contiene su hash.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
OUT="$ROOT/build/sql/first-account.sql"

cp -R "$ROOT/backend/src" "$ROOT/backend/bin" "$ROOT/backend/database" "$ROOT/backend/bootstrap.php" "$TMP/"
mkdir -p "$TMP/var"
printf 'APP_ENV=development\nDB_DRIVER=sqlite\nDB_PATH=var/account.sqlite\n' > "$TMP/.env"
php "$TMP/bin/migrate.php" >/dev/null

. "$ROOT/deploy/prompt.sh"
ask_text ORG "Nombre de la corredora (ej. Propiedades Mar)" 2
ask_text NAME "Nombre de la persona administradora" 2
ask_email EMAIL "Correo de acceso de la corredora (distinto al del superadmin si quieres separarlos)"
ask_password PASS "Contraseña de la corredora"

# JSON armado por PHP desde variables de entorno: sin problemas de comillas y sin la contraseña en argumentos.
NAME="$NAME" EMAIL="$EMAIL" ORG="$ORG" PASS="$PASS" php -r 'echo json_encode(["name"=>getenv("NAME"),"email"=>getenv("EMAIL"),"password"=>getenv("PASS"),"organizationName"=>getenv("ORG"),"kind"=>"broker"]);' \
  | php "$TMP/bin/create-account.php" >/dev/null
unset PASS

mkdir -p "$(dirname "$OUT")"
umask 077
{
  echo "-- Primera cuenta de Panorama. Importar UNA vez, después de install.sql."
  for t in users organizations memberships subscriptions; do
    sqlite3 "$TMP/var/account.sqlite" ".mode insert $t" "SELECT * FROM $t;"
  done
} > "$OUT"
echo "Generado build/sql/first-account.sql ($(grep -c '^INSERT' "$OUT") filas). Contiene el hash de la contraseña: no lo compartas ni lo subas."
