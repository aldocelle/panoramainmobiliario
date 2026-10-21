<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
use Panorama\{
    Config,
    Database,
    Application,
    Request,
    Response,
    ApiError,
    Support,
};
ini_set("display_errors", "0");
try {
    $config = Config::read();
    $db = new Database($config);
    // Schema changes are CLI-only: never migrate during an HTTP request.
    $request = Request::globals();
    (new Application($db, $config))->handle($request)->send();
} catch (ApiError $e) {
    (new Response(
        ["error" => ["code" => $e->errorCode, "message" => $e->getMessage()]],
        $e->status,
        ["Cache-Control" => "no-store", "X-Content-Type-Options" => "nosniff"],
    ))->send();
} catch (Throwable $e) {
    $id = Support::uuid();
    error_log("Panorama bootstrap " . $id . " " . get_class($e));
    (new Response(
        [
            "error" => [
                "code" => "SERVICE_UNAVAILABLE",
                "message" => "Servicio no disponible.",
                "requestId" => $id,
            ],
        ],
        503,
        ["Cache-Control" => "no-store"],
    ))->send();
}
