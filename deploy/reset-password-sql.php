<?php
declare(strict_types=1);
// Imprime el SQL de cambio de contraseña. Lo usa deploy/reset-password-sql.sh; datos por variables de entorno
// (KIND=1 corredora, 2 superadmin; EMAIL; PASS) para que la contraseña no aparezca en argumentos.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
$q = fn(string $v): string => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
$email = $q(strtolower(trim((string) getenv("EMAIL"))));
$hash = $q(password_hash((string) getenv("PASS"), PASSWORD_DEFAULT));
echo "-- Cambio de contraseña. Importar en phpMyAdmin: el UPDATE debe afectar 1 fila.\n";
if (getenv("KIND") === "1") {
    echo "UPDATE users SET password_hash=$hash WHERE email=$email;\n";
    echo "DELETE FROM sessions WHERE user_id IN (SELECT id FROM (SELECT id FROM users WHERE email=$email) u);\n";
} else {
    echo "UPDATE platform_admins SET password_hash=$hash WHERE email=$email;\n";
    echo "DELETE FROM platform_sessions WHERE admin_id IN (SELECT id FROM (SELECT id FROM platform_admins WHERE email=$email) a);\n";
}
