<?php
declare(strict_types=1);
// Solo terminal: si el servidor web llega a ejecutar este archivo, responde 404 sin hacer nada.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . "/bootstrap.php";
// Input JSON via stdin: passwords never appear in process arguments.
try {
    $input = json_decode(
        stream_get_contents(STDIN),
        true,
        32,
        JSON_THROW_ON_ERROR,
    );
    if (!is_array($input)) {
        throw new RuntimeException("Entrada inválida.");
    }
    $config = Panorama\Config::read();
    $db = new Panorama\Database($config);
    [$user, $org] = (new Panorama\Auth($db, $config))->createAccount($input);
    echo Panorama\Support::json([
    "userId" => $user,
    "organizationId" => $org,
    "subscription" => "pilot",
    "publicationLimit" => 5,
]),
        PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo crear la cuenta: " . $e->getMessage() . "\n");
    exit(1);
}
