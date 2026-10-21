#!/usr/bin/env bash
# Genera build/sql/reset-password.sql para cambiar la contraseña de una cuenta en producción desde
# phpMyAdmin (mientras el correo de recuperación no esté activo). Cierra las sesiones abiertas de esa cuenta.
# La contraseña se escribe aquí, en tu equipo; el archivo solo contiene su hash.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/build/sql/reset-password.sql"
[ -t 0 ] || { echo "ERROR: ejecútalo en una terminal (VS Code: Terminal → Nueva terminal)." >&2; exit 1; }
. "$ROOT/deploy/prompt.sh"
while :; do read -r -p "¿Qué cuenta? 1 = corredora, 2 = superadmin: " KIND; [[ "$KIND" =~ ^[12]$ ]] && break; done
ask_email EMAIL "Correo de la cuenta"
ask_password PASS "Contraseña nueva"
mkdir -p "$(dirname "$OUT")"; umask 077
KIND="$KIND" EMAIL="$EMAIL" PASS="$PASS" php "$ROOT/deploy/reset-password-sql.php" > "$OUT"
unset PASS
echo "Generado build/sql/reset-password.sql. Impórtalo en phpMyAdmin y luego bórralo (contiene el hash)."
