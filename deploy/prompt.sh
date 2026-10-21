# Funciones de pregunta compartidas por los scripts de despliegue. Repiten la pregunta hasta tener un valor válido.
ask_text() { # ask_text VAR "Pregunta" min
  local v; while :; do read -r -p "$2: " v; v="$(echo "$v" | sed 's/^ *//;s/ *$//')"
    [ "${#v}" -ge "$3" ] && break; echo "  → Escribe al menos $3 caracteres."; done; printf -v "$1" '%s' "$v"; }
ask_email() { # ask_email VAR "Pregunta"
  local v; while :; do read -r -p "$2: " v; v="$(echo "$v" | tr -d ' ' | tr 'A-Z' 'a-z')"
    [[ "$v" =~ ^[^@]+@[^@]+\.[^@]+$ ]] && break; echo "  → Correo inválido."; done; printf -v "$1" '%s' "$v"; }
ask_password() { # ask_password VAR "Pregunta"
  local a b; while :; do read -r -s -p "$2 (12 a 72 caracteres, no se ve al escribir): " a; echo
    if [ "${#a}" -lt 12 ] || [ "${#a}" -gt 72 ]; then echo "  → Debe tener entre 12 y 72 caracteres (escribiste ${#a})."; continue; fi
    read -r -s -p "Repite la contraseña: " b; echo
    [ "$a" = "$b" ] && break; echo "  → No coinciden. Intenta de nuevo."; done; printf -v "$1" '%s' "$a"; }
