<?php
declare(strict_types=1);
// Solo terminal: si el servidor web llega a ejecutar este archivo, responde 404 sin hacer nada.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . "/bootstrap.php";
// Restricted administrative command: loads a broker's existing portfolio.
// Input JSON via stdin: {"organizationId","userId","listings":[{"source","photos":[paths],"property":{...}}]}
// (see scripts/listings-to-json.mjs). Uses the same services as the API, so validation,
// WebP re-encoding, quota checks and audit apply; only the network rate limit is skipped.
// Listings whose title already exists in the organization are skipped, so reruns are safe.
use Panorama\{Config, Database, Auth, MediaService, PropertyService, Request, Validator, ApiError, Support};
// Igual que el navegador antes de subir: fotos sobre 2560 px se reducen (JPEG 90) para entrar en los
// límites de la API; el servidor luego las optimiza a 1920 px y crea la miniatura.
function fitForUpload(string $bytes): string
{
    $size = @getimagesizefromstring($bytes);
    if (!$size || max($size[0], $size[1]) <= 2560 || !($image = @imagecreatefromstring($bytes))) {
        return $bytes;
    }
    if ($size[2] === IMAGETYPE_JPEG) {
        $image = Panorama\MediaService::orient($image, $bytes);
    }
    $w = imagesx($image);
    $h = imagesy($image);
    $ratio = 2560 / max($w, $h);
    $scaled = imagecreatetruecolor((int) round($w * $ratio), (int) round($h * $ratio));
    imagecopyresampled($scaled, $image, 0, 0, 0, 0, imagesx($scaled), imagesy($scaled), $w, $h);
    ob_start();
    imagejpeg($scaled, null, 90);
    return ob_get_clean();
}
try {
    $input = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
    Validator::keys($input, ["organizationId", "userId", "listings"]);
    $org = Validator::id($input, "organizationId");
    $user = Validator::id($input, "userId");
    $config = Config::read();
    $db = new Database($config);
    $member = Support::record(
        $db->one("SELECT role FROM memberships WHERE organization_id=? AND user_id=?", [$org, $user]),
    );
    $auth = new Auth($db, $config);
    $media = new MediaService($db, $auth, $config);
    $properties = new PropertyService($db, $auth, $config);
    $request = function (string $path, array $body) use ($config, $org, $user, $member): Request {
        $r = new Request("POST", $config["prefix"] . $path, [], [], [], $body, "127.0.0.1");
        $r->auth = ["userId" => $user, "organizationId" => $org, "role" => $member["role"], "csrf" => "", "sessionHash" => ""];
        return $r;
    };
    $created = 0;
    foreach ($input["listings"] as $listing) {
        $title = $listing["property"]["title"] ?? "";
        if ($db->one("SELECT id FROM properties WHERE organization_id=? AND title=? AND deleted_at IS NULL", [$org, $title])) {
            echo "= ya existe: $title\n";
            continue;
        }
        $urls = [];
        foreach ($listing["photos"] as $path) {
            $bytes = is_file($path) ? file_get_contents($path) : false;
            if ($bytes === false || strlen($bytes) > 4194304) {
                echo "  ! foto omitida (no legible o > 4 MiB): " . basename($path) . "\n";
                continue;
            }
            try {
                $urls[] = $media->upload($request("/media", ["contentBase64" => base64_encode(fitForUpload($bytes))]))->data["url"];
            } catch (ApiError $e) {
                echo "  ! foto omitida (" . basename($path) . "): {$e->getMessage()}\n";
            }
        }
        $p = $properties->create($request("/properties", ["images" => $urls] + $listing["property"]))->data;
        $created++;
        echo "+ {$p["status"]}: $title (" . count($urls) . " fotos)\n";
    }
    echo "Listo: $created propiedades creadas.\n";
} catch (ApiError $e) {
    fwrite(STDERR, "No se pudo importar ({$e->errorCode}): {$e->getMessage()}\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "No se pudo importar: " . $e->getMessage() . "\n");
    exit(1);
}
