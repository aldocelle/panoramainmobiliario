#!/usr/bin/env bash
# Sube SOLO la interfaz (index.html, assets/, favicon, og-image) a /panorama por FTPS.
# - No toca api/, app/ ni la base de datos; no pide la contraseña de la base.
# - Nunca borra archivos del servidor (mirror sin --delete). Muestra vista previa y pide confirmación escrita.
# - La contraseña FTP se pide en esta terminal (o desde LFTP_PASSWORD); no queda en archivos ni en el historial.
# Uso: deploy/upload-ui.sh            (ejecutar en una terminal real)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/deploy/production.env"
fail() { echo "ERROR: $*" >&2; exit 1; }
[ -t 0 ] || fail "ejecútalo en una terminal (VS Code: Terminal → Nueva terminal)."
[ -f "$ENV_FILE" ] || fail "falta $ENV_FILE."
command -v lftp >/dev/null || fail "falta lftp (brew install lftp)."
get() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -1; }
HOST="${FTP_HOST:-a0031369.ferozo.com}"
FTP_LOGIN="${FTP_USER:-panorama@expresswebsite.cl}"
REMOTE="${FTP_REMOTE_DIR:-/panorama}"

echo "› Pruebas del frontend"
(cd "$ROOT" && npm test >/dev/null) || fail "hay pruebas fallando; no se sube nada."

echo "› Compilando la interfaz"
SITE_URL="$(get ALLOWED_ORIGINS | cut -d, -f1)$(get API_PREFIX | sed 's#/api/v1$##')"
(cd "$ROOT" && PANORAMA_SITE_URL="$SITE_URL" npm run build >/dev/null)
grep -q "$SITE_URL/og-image.jpg" "$ROOT/dist/index.html" || fail "index.html no quedó con la URL del sitio ($SITE_URL)."
for key in VITE_DEMO_PASSWORD VITE_SUPERADMIN_PASSWORD; do
  DEMO_PASS="$(sed -n "s/^$key=//p" "$ROOT/.env.development.local" 2>/dev/null || true)"
  if [ -n "$DEMO_PASS" ] && grep -rqF "$DEMO_PASS" "$ROOT/dist"; then fail "la interfaz compilada contiene una contraseña de prueba ($key)."; fi
done
unset DEMO_PASS

if [ -z "${LFTP_PASSWORD:-}" ]; then
  read -r -s -p "Contraseña FTP de $FTP_LOGIN: " LFTP_PASSWORD; echo
  export LFTP_PASSWORD
fi
SETTINGS="set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate ${FTP_VERIFY_CERT:-yes}; set net:max-retries 2; set net:timeout 20"
run() { lftp --env-password -u "$FTP_LOGIN" -e "$SETTINGS; $1; bye" "$HOST"; }

echo; echo "› Vista previa de la interfaz que se subirá a $REMOTE (no se borra nada, api/ y app/ no se tocan):"
run "mirror --reverse --dry-run --no-perms '$ROOT/dist' '$REMOTE'"
echo
read -r -p "¿Subir la interfaz a $REMOTE? Escribe SUBIR para continuar: " answer
[ "$answer" = "SUBIR" ] || { echo "Cancelado. No se subió nada."; exit 1; }
run "mirror --reverse --no-perms --parallel=3 --verbose=1 '$ROOT/dist' '$REMOTE'"
echo; echo "Interfaz publicada. Abre $SITE_URL/ y recarga con Cmd+Shift+R."
