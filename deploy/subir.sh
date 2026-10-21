#!/usr/bin/env bash
# Actualización rápida de producción (DonWeb/Ferozo) por FTPS. Un comando:
#   deploy/subir.sh          interfaz + API (no toca app/.env ni las fotos ya subidas del servidor)
#   deploy/subir.sh --ui     solo la interfaz (index.html, assets)
#   deploy/subir.sh --yes    sin pregunta final (igual muestra el resumen)
# - La contraseña FTP se pide una sola vez y queda en el Llavero de macOS (servicio "panorama-ftp").
#   Para cambiarla u olvidarla: security delete-generic-password -s panorama-ftp
# - Sube solo lo que cambió (compara tamaño) y nunca borra archivos del servidor.
# - Detecta si la cuenta FTP abre en la raíz o dentro de /panorama.
# - Si hay migraciones nuevas, indica el archivo para phpMyAdmin y pide confirmar que se importó.
# Primera instalación: deploy/publish.sh.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
fail() { echo "ERROR: $*" >&2; exit 1; }
UI=0; YES=0
for arg in "$@"; do case "$arg" in --ui) UI=1 ;; --yes|-y) YES=1 ;; *) fail "opción desconocida: $arg" ;; esac; done
[ -t 0 ] || [ "$YES" = 1 ] || fail "ejecútalo en una terminal (VS Code: Terminal → Nueva terminal)."
command -v lftp >/dev/null || fail "falta lftp (brew install lftp)."
ENV_FILE="$ROOT/deploy/production.env"
[ -f "$ENV_FILE" ] || fail "falta $ENV_FILE."
get() { sed -n "s/^$1=//p" "$ENV_FILE" | tail -1; }
HOST="${FTP_HOST:-a0031369.ferozo.com}"
FTP_LOGIN="${FTP_USER:-panorama@expresswebsite.cl}"
KEYCHAIN_SERVICE="panorama-ftp"
STATE="$ROOT/deploy/.production-migration"   # última migración importada en producción (local, no versionado)
NEWEST="$(ls backend/database/migrations/*.sql | xargs -n1 basename | tail -1)"

# 1. Contraseña FTP: variable de entorno, Llavero o pregunta (y luego se guarda en el Llavero).
SAVE_KEYCHAIN=0
if [ -z "${LFTP_PASSWORD:-}" ]; then
  LFTP_PASSWORD="$(security find-generic-password -s "$KEYCHAIN_SERVICE" -a "$FTP_LOGIN" -w 2>/dev/null || true)"
  if [ -z "$LFTP_PASSWORD" ]; then
    [ "$YES" = 0 ] || fail "no hay contraseña en el Llavero; ejecútalo una vez sin --yes."
    read -r -s -p "Contraseña FTP de $FTP_LOGIN (se guardará en tu Llavero): " LFTP_PASSWORD; echo
    [ -n "$LFTP_PASSWORD" ] || fail "contraseña vacía."
    SAVE_KEYCHAIN=1
  fi
fi
export LFTP_PASSWORD
SETTINGS="set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate ${FTP_VERIFY_CERT:-yes}; set net:max-retries 2; set net:timeout 20"
run() { lftp --env-password -u "$FTP_LOGIN" -e "$SETTINGS; $1; bye" "$HOST"; }

# 2. Carpeta del sitio: /panorama, o la raíz si la cuenta FTP abre dentro de ella.
echo "› Conectando con $HOST"
LIST_R="$(run "cls -1F /" 2>&1)" || fail "no se pudo entrar por FTP (¿contraseña?): $(tail -1 <<<"$LIST_R")"
if [ "$SAVE_KEYCHAIN" = 1 ]; then
  # Por la entrada estándar de "security -i": la contraseña no aparece en la lista de procesos.
  esc="${LFTP_PASSWORD//\\/\\\\}"; esc="${esc//\"/\\\"}"
  if printf 'add-generic-password -U -s %s -a "%s" -w "%s"\n' "$KEYCHAIN_SERVICE" "$FTP_LOGIN" "$esc" | security -i >/dev/null 2>&1; then
    echo "  Contraseña guardada en el Llavero: no se volverá a pedir."
  else
    echo "  No se pudo guardar en el Llavero; se pedirá de nuevo la próxima vez."
  fi
  unset esc
fi
if [ -n "${FTP_REMOTE_DIR:-}" ]; then
  REMOTE="$FTP_REMOTE_DIR"
elif grep -qx "api/" <<<"$LIST_R" && grep -qx "app/" <<<"$LIST_R"; then
  REMOTE="/"
elif grep -qx "panorama/" <<<"$LIST_R"; then
  REMOTE="/panorama"
else
  echo "No reconozco dónde está el sitio. Raíz de la cuenta FTP:"; sed 's/^/  /' <<<"$LIST_R"
  fail "indica la carpeta, por ejemplo: FTP_REMOTE_DIR=/ deploy/subir.sh"
fi
echo "  Carpeta del sitio: $REMOTE"
if [ "$UI" = 0 ]; then
  run "cls -1 '${REMOTE%/}/app/.env'" >/dev/null 2>&1 || fail "el servidor no tiene ${REMOTE%/}/app/.env: el sitio no está instalado ahí. Si es la primera instalación usa deploy/publish.sh; si el sitio está en otra carpeta, usa FTP_REMOTE_DIR=/ruta deploy/subir.sh"
fi

# 3. Paquete.
echo "› Pruebas y compilación"
if [ "$UI" = 1 ]; then
  npm test >/dev/null || fail "hay pruebas fallando; no se sube nada."
  SITE_URL="$(get ALLOWED_ORIGINS | cut -d, -f1)$(get API_PREFIX | sed 's#/api/v1$##')"
  PANORAMA_SITE_URL="$SITE_URL" npm run build >/dev/null
  SRC="$ROOT/dist"
else
  PANORAMA_NO_DB_ENV=1 deploy/build.sh >/dev/null || fail "deploy/build.sh falló; ejecútalo solo para ver el detalle."
  SRC="$ROOT/build/panorama"
fi
[ -f "$SRC/index.html" ] || fail "no se generó $SRC/index.html."

# 4. Migraciones: deben estar importadas en phpMyAdmin antes de subir el código.
if [ "$UI" = 0 ] && [ "$(cat "$STATE" 2>/dev/null || true)" != "$NEWEST" ]; then
  LAST="$(cat "$STATE" 2>/dev/null || true)"
  echo; echo "› Migraciones que producción debe tener importadas (phpMyAdmin → base a0031369_pano → Importar):"
  for f in backend/database/migrations/*.sql; do
    name="$(basename "$f")"
    if [ -z "$LAST" ] || [[ "$name" > "$LAST" ]]; then echo "    build/sql/updates/$name"; fi
  done
  [ -z "$LAST" ] && echo "  (Primera vez con este script: las que ya estaban importadas no se vuelven a importar.)"
  [ "$YES" = 0 ] || fail "hay migraciones sin confirmar; ejecútalo sin --yes."
  read -r -p "¿Producción ya tiene importadas todas hasta $NEWEST? [s/N] " ok
  [[ "$ok" =~ ^[sS]$ ]] || { echo "Importa las que falten y vuelve a ejecutar. No se subió nada."; exit 1; }
  echo "$NEWEST" > "$STATE"
fi

# 5. Qué cambió (tamaño distinto o archivo nuevo). Nada se borra; app/.env del servidor queda intacto.
MIRROR="mirror --reverse --no-perms --ignore-time --exclude-glob .DS_Store --exclude-glob app/.env"
PLAN="$(run "$MIRROR --dry-run '$SRC' '$REMOTE'" 2>&1)" || fail "no se pudo comparar con el servidor: $(tail -1 <<<"$PLAN")"
CHANGES="$(grep -E '^(get|put) ' <<<"$PLAN" | sed -E 's#.*file:'"$SRC"'/##' || true)"
COUNT="$(grep -c . <<<"$CHANGES" || true)"
if [ "$COUNT" = 0 ]; then echo; echo "El servidor ya está al día. No hay nada que subir."; exit 0; fi
echo; echo "› $COUNT archivos nuevos o cambiados:"
head -15 <<<"$CHANGES" | sed 's/^/    /'
[ "$COUNT" -le 15 ] || echo "    … y $((COUNT - 15)) más"
if [ "$YES" = 0 ]; then
  read -r -p "¿Subirlos a $HOST:$REMOTE? [s/N] " ok
  [[ "$ok" =~ ^[sS]$ ]] || { echo "Cancelado. No se subió nada."; exit 1; }
fi

# 6. Subida.
echo "› Subiendo…"
run "$MIRROR --parallel=8 '$SRC' '$REMOTE'" >/dev/null || fail "la subida se interrumpió; vuelve a ejecutar (solo sube lo que falte)."
echo; echo "Listo: $COUNT archivos publicados. Abre el sitio y recarga con Cmd+Shift+R."
