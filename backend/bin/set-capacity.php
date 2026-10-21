<?php
declare(strict_types=1);
// Solo terminal: si el servidor web llega a ejecutar este archivo, responde 404 sin hacer nada.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . "/bootstrap.php";
// Restricted server-side administrative command, not exposed as an HTTP API.
try {
    $input = json_decode(
        stream_get_contents(STDIN),
        true,
        32,
        JSON_THROW_ON_ERROR,
    );
    Panorama\Validator::keys($input, [
        "organizationId",
        "limit",
        "status",
        "reason",
    ]);
    $org = Panorama\Validator::id($input, "organizationId");
    $limit = Panorama\Validator::number($input, "limit", 0, 100000, true);
    $status = Panorama\Validator::enum($input, "status", [
        "pilot",
        "active",
        "suspended",
        "expired",
    ]);
    $reason = Panorama\Validator::string($input, "reason", 5, 300);
    $db = new Panorama\Database(Panorama\Config::read());
    Panorama\PlatformService::changeCapacity(
        $db,
        new Panorama\Request("CLI", "capacity"),
        $org,
        $limit,
        $status,
        $reason,
        "cli",
    );
    echo "Capacidad actualizada y motivo registrado.", PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
