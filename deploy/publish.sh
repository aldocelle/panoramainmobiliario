#!/usr/bin/env bash
# Publicación completa en un solo comando. Ejecutar en una terminal real (pide contraseñas sin mostrarlas).
#   1. Arma el paquete (pide la contraseña de la base).
#   2. Primera vez: genera build/sql/panorama.sql con esquema + superadmin + primera cuenta,
#      y espera a que lo importes en phpMyAdmin.
#   3. Sube /panorama por FTPS (pide la contraseña FTP y confirmación SUBIR).
# Uso: deploy/publish.sh            (primera instalación)
#      deploy/publish.sh --update   (versiones siguientes: sin SQL inicial)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
[ -t 0 ] || { echo "ERROR: ejecútalo en una terminal (VS Code: Terminal → Nueva terminal), no con ! en el chat." >&2; exit 1; }
export FTP_HOST="${FTP_HOST:-a0031369.ferozo.com}"
export FTP_USER="${FTP_USER:-panorama@expresswebsite.cl}"

echo "== 1/3 Paquete =="
# Solo en la primera instalación se ofrece reutilizar un paquete recién armado (si un intento se detuvo
# después del armado). Una actualización siempre rearma: llevar código y fotos nuevos es su objetivo.
if [ "${1:-}" != "--update" ] && [ -f build/panorama/app/.env ] && read -r -p "Ya hay un paquete armado. ¿Reutilizarlo? (s/n): " reuse && [ "$reuse" = "s" ]; then
  echo "Reutilizando build/panorama."
else
  deploy/build.sh
fi

if [ "${1:-}" != "--update" ]; then
  echo; echo "== 2/3 Base de datos (solo la primera vez) =="
  echo "-- Superadmin de la plataforma --"; deploy/superadmin-sql.sh
  echo "-- Primera corredora --"; deploy/first-account-sql.sh
  umask 077
  cat build/sql/install.sql build/sql/superadmin.sql build/sql/first-account.sql > build/sql/panorama.sql
  echo
  echo "Abre phpMyAdmin en el panel de Ferozo, entra a la base a0031369_pano (vacía),"
  echo "pestaña Importar, y sube este archivo:"
  echo "  $ROOT/build/sql/panorama.sql"
  read -r -p "Cuando la importación termine sin errores, escribe LISTO: " ok
  [ "$ok" = "LISTO" ] || { echo "Detenido antes de subir archivos."; exit 1; }
fi

echo; echo "== 3/3 Subida a /panorama =="
deploy/upload.sh
rm -f build/sql/superadmin.sql build/sql/first-account.sql
echo "Archivos SQL con hashes de contraseña eliminados de build/sql."
