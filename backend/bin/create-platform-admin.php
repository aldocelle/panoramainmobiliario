<?php
declare(strict_types=1);
// Solo terminal: si el servidor web llega a ejecutar este archivo, responde 404 sin hacer nada.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . "/bootstrap.php";
// Crea un superadmin de plataforma. Entrada JSON por stdin: {"name","email","password"}.
// Solo por terminal: no existe ruta HTTP para crear superadmins.
use Panorama\{Config, Database, Validator, Support, ApiError};
try {
    $input = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);
    Validator::keys($input, ["name", "email", "password"]);
    $email = Validator::email($input);
    $name = Validator::string($input, "name", 2, 100);
    $hash = password_hash(Validator::password($input), PASSWORD_DEFAULT);
    $db = new Database(Config::read());
    ApiError::require(
        !$db->one("SELECT id FROM platform_admins WHERE email=?", [$email]),
        409,
        "ADMIN_EXISTS",
        "Ya existe un superadmin con ese correo.",
    );
    $id = Support::uuid();
    $db->run("INSERT INTO platform_admins VALUES(?,?,?,?,?,?)", [$id, $email, $name, $hash, 1, Support::now()]);
    echo Support::json(["adminId" => $id, "email" => $email]), PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo crear el superadmin: " . $e->getMessage() . "\n");
    exit(1);
}
