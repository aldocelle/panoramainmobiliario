<?php
declare(strict_types=1);
// Solo terminal: si el servidor web llega a ejecutar este archivo, responde 404 sin hacer nada.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . "/bootstrap.php";
$config = Panorama\Config::read();
if ($config["driver"] !== "sqlite") {
    fwrite(
        STDERR,
        "Para MySQL/MariaDB usa el respaldo nativo del proveedor o mariadb-dump con credenciales protegidas y verificación de restauración.\n",
    );
    exit(1);
}
$target = $argv[1] ?? "";
if ($target === "" || file_exists($target) || !is_dir(dirname($target))) {
    fwrite(
        STDERR,
        "Indica una ruta nueva, fuera del document root y en un directorio existente.\n",
    );
    exit(1);
}
$db = new Panorama\Database($config);
$db->pdo->exec("VACUUM INTO " . $db->pdo->quote($target));
chmod($target, 0600);
echo "Respaldo SQLite creado.\n";
