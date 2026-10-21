<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
// Restricted administrative command: loads a broker's existing portfolio.
// Input JSON via stdin: {"organizationId","userId","listings":[{"source","photos":[paths],"property":{...}}]}
// (see scripts/listings-to-json.mjs). Uses the same services as the API, so validation,
// WebP re-encoding, quota checks and audit apply; only the network rate limit is skipped.
// Listings whose title already exists in the organization are skipped, so reruns are safe.
use Panorama\{Config, Database, Auth, MediaService, PropertyService, Request, Validator, ApiError, Support};
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
            $urls[] = $media->upload($request("/media", ["contentBase64" => base64_encode($bytes)]))->data["url"];
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
