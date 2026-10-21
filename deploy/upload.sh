#!/usr/bin/env bash
# Sube build/panorama/ a la carpeta /panorama del hosting por FTP cifrado (FTPS).
# - Solo escribe dentro de /panorama. Nunca borra archivos del servidor (mirror sin --delete).
# - Muestra primero una vista previa y pide confirmación escrita.
# - La contraseña se pide en esta terminal (o desde LFTP_PASSWORD); no queda en archivos ni en el historial.
# Uso: FTP_HOST=a0031369.ferozo.com FTP_USER=usuario deploy/upload.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/build/panorama"
# Si tu cuenta FTP abre directamente dentro de la carpeta panorama, usa FTP_REMOTE_DIR=/
REMOTE="${FTP_REMOTE_DIR:-/panorama}"
HOST="${FTP_HOST:?Define FTP_HOST (servidor FTP, sin ftp://)}"
FTP_LOGIN="${FTP_USER:?Define FTP_USER}"

[ -f "$SRC/index.html" ] && [ -f "$SRC/api/index.php" ] && [ -f "$SRC/app/.env" ] || {
  echo "ERROR: no hay paquete completo en build/panorama. Ejecuta deploy/build.sh primero." >&2; exit 1; }
command -v lftp >/dev/null || { echo "ERROR: falta lftp (brew install lftp)." >&2; exit 1; }

if [ -z "${LFTP_PASSWORD:-}" ]; then
  read -r -s -p "Contraseña FTP de $FTP_LOGIN: " LFTP_PASSWORD; echo
  export LFTP_PASSWORD
fi

# FTPS obligatorio. Si el certificado del servidor no coincide con el nombre, se detiene:
# solo usa FTP_VERIFY_CERT=no si confirmaste el servidor en el panel del hosting.
SETTINGS="set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate ${FTP_VERIFY_CERT:-yes}; set net:max-retries 2; set net:timeout 20"
run() { lftp --env-password -u "$FTP_LOGIN" -e "$SETTINGS; $1; bye" "$HOST"; }

echo "› Contenido actual de $REMOTE en $HOST:"
run "cls -l $REMOTE/" 2>/dev/null || echo "  (la carpeta no existe todavía; se creará)"

echo; echo "› Vista previa de lo que se subirá (no se borra nada):"
run "mirror --reverse --dry-run --no-perms '$SRC' '$REMOTE'"

echo
read -r -p "¿Subir estos archivos a $REMOTE? Escribe SUBIR para continuar: " answer
[ "$answer" = "SUBIR" ] || { echo "Cancelado. No se subió nada."; exit 1; }

run "mirror --reverse --no-perms --parallel=3 --verbose=1 '$SRC' '$REMOTE'"
PREFIX="$(sed -n 's/^API_PREFIX=//p' "$SRC/app/.env")"; ORIGIN="$(sed -n 's/^ALLOWED_ORIGINS=//p' "$SRC/app/.env")"
echo; echo "Subida terminada. Comprueba: $ORIGIN$PREFIX/health"
