<?php
declare(strict_types=1);
// Solo terminal: si el servidor web llega a ejecutar este archivo, responde 404 sin hacer nada.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
echo bin2hex(random_bytes(32)), PHP_EOL;
