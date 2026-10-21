<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
use Panorama\{Application, Auth, Config, Database, Request};
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$config = Config::read();
$config["production"] = false;
$config["secure"] = false;
if (
    $config["driver"] !== "mysql" ||
    !str_starts_with($config["database"], "panorama_test_")
) {
    exit(2);
}
$app = new Application(new Database($config), $config);
// A shared target start time makes independent processes race at the quota check.
$delay = (int) (($input["start"] - microtime(true)) * 1000000);
if ($delay > 0) {
    usleep(min($delay, 2000000));
}
$response = $app->handle(
    new Request(
        "POST",
        "/api/v1/properties",
        [
            "content-type" => "application/json",
            "x-csrf-token" => $input["csrf"],
        ],
        [],
        [Auth::COOKIE => $input["cookie"]],
        $input["property"],
        "192.0.2.150",
    ),
);
echo json_encode(
    [
        "status" => $response->status,
        "code" => $response->data["error"]["code"] ?? null,
    ],
    JSON_THROW_ON_ERROR,
);
