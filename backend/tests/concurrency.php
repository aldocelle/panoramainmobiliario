<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
use Panorama\{Application, Auth, Config, Database, Request, Support};
$config = Config::read();
if (
    $config["driver"] !== "mysql" ||
    !str_starts_with($config["database"], "panorama_test_")
) {
    throw new RuntimeException("Usa una base MySQL de prueba panorama_test_.");
}
$config["production"] = false;
$config["registration"] = true;
$config["secure"] = false;
$db = new Database($config);
$db->migrate();
$auth = new Auth($db, $config);
$email = Support::uuid() . "@example.com";
$user = $auth->createAccount([
    "name" => "Race Test",
    "organizationName" => "Race Test",
    "email" => $email,
    "password" => "Strong concurrency test 123!",
    "kind" => "broker",
]);
// Authenticate through the same API flow as a browser.
$app = new Application($db, $config);
$login = $app->handle(
    new Request(
        "POST",
        "/api/v1/auth/login",
        [],
        [],
        [],
        ["email" => $email, "password" => "Strong concurrency test 123!"],
    ),
);
if ($login->status !== 200) {
    throw new RuntimeException("No se pudo crear sesión de prueba.");
}
$db->run(
    "UPDATE subscriptions SET publication_limit=1 WHERE organization_id=?",
    [$user[1]],
);
$input = [
    "start" => microtime(true) + 0.5,
    "cookie" => $login->cookie["value"],
    "csrf" => $login->data["csrfToken"],
    "property" => [
        "title" => "Publicación simultánea",
        "type" => "Casa",
        "operation" => "Venta",
        "currency" => "UF",
        "price" => 1000,
        "region" => "13",
        "commune" => "Santiago",
        "sector" => "",
        "privateAddress" => "",
        "area" => 50,
        "beds" => 1,
        "baths" => 1,
        "description" => "Prueba de concurrencia",
        "images" => [],
        "status" => "Publicada",
    ],
];
$processes = [];
$command = getenv("PANORAMA_PHP_COMMAND") ?: PHP_BINARY;
for ($i = 0; $i < 2; $i++) {
    $process = proc_open(
        [$command, __DIR__ . "/publication-worker.php"],
        [["pipe", "r"], ["pipe", "w"], ["pipe", "w"]],
        $pipes,
    );
    if (!is_resource($process)) {
        throw new RuntimeException("No se pudo ejecutar el proceso.");
    }
    fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $processes[] = [$process, $pipes];
}
$statuses = [];
foreach ($processes as [$process, $pipes]) {
    $data = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0) {
        throw new RuntimeException("Falló proceso de prueba: " . $err);
    }
    $statuses[] = json_decode($data, true, 32, JSON_THROW_ON_ERROR)["status"];
}
sort($statuses);
$count = (int) $db->one(
    "SELECT COUNT(*) total FROM properties WHERE organization_id=? AND status='Publicada'",
    [$user[1]],
)["total"];
if ($statuses !== [201, 409] || $count !== 1) {
    throw new RuntimeException(
        "La concurrencia excedió el cupo: " . json_encode($statuses),
    );
}
echo "PASS dos procesos independientes: un aviso publicado y un rechazo por cupo.\n";
