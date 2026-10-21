#!/usr/bin/env bash
# Genera build/sql/superadmin.sql para crear el superadmin de producción desde phpMyAdmin (sin SSH).
# La contraseña se escribe aquí, en tu equipo; el archivo solo contiene su hash.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/build/sql/superadmin.sql"
read -r -p "Nombre del superadmin: " NAME
read -r -p "Correo de acceso: " EMAIL
read -r -s -p "Contraseña (12 a 72 caracteres): " PASS; echo
read -r -s -p "Repite la contraseña: " PASS2; echo
[ "$PASS" = "$PASS2" ] || { echo "Las contraseñas no coinciden." >&2; exit 1; }
mkdir -p "$(dirname "$OUT")"; umask 077
NAME="$NAME" EMAIL="$EMAIL" PASS="$PASS" php -r '
$email=strtolower(trim(getenv("EMAIL")));$name=trim(getenv("NAME"));$pass=getenv("PASS");
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<12||strlen($pass)>72||mb_strlen($name)<2){fwrite(STDERR,"Datos inválidos.\n");exit(1);}
$q=fn($v)=>"'"'"'".str_replace("'"'"'","'"'"''"'"'",$v)."'"'"'";
$id=sprintf("%s-%s-4%s-%s%s-%s",bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),substr(bin2hex(random_bytes(2)),1),"89ab"[random_int(0,3)],substr(bin2hex(random_bytes(2)),1),bin2hex(random_bytes(6)));
echo "-- Superadmin de Panorama. Importar UNA vez, después de install.sql.\n";
echo "INSERT INTO platform_admins VALUES(",$q($id),",",$q($email),",",$q($name),",",$q(password_hash($pass,PASSWORD_DEFAULT)),",1,",$q(gmdate("Y-m-d\TH:i:s\Z")),");\n";' > "$OUT"
unset PASS PASS2
echo "Generado build/sql/superadmin.sql. Contiene el hash de la contraseña: no lo compartas ni lo subas."
