<?php
declare(strict_types=1);
// Exporta la cartera de una corredora de la base local a otra instalación (p. ej. producción sin SSH).
// Genera export/portfolio.sql (propiedades, fotos y cupo) y copia las fotos a export/media/,
// que deploy/build.sh incluye en app/var/media del paquete.
// Uso: php deploy/portfolio-export.php <id-org-local> <id-org-destino> <prefijo-api-destino> <cupo>
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
[$_, $from, $to, $prefix, $limit] = $argv + [null, null, null, null, null];
$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
if (!preg_match($uuid, (string) $from) || !preg_match($uuid, (string) $to) || !preg_match('~^(/[A-Za-z0-9_-]+)*/api/v1$~', (string) $prefix) || !ctype_digit((string) $limit)) {
    fwrite(STDERR, "Uso: php deploy/portfolio-export.php <id-org-local> <id-org-destino> <prefijo-api> <cupo>\n");
    exit(1);
}
$root = dirname(__DIR__);
$pdo = new PDO("sqlite:$root/backend/var/development.sqlite", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
// Literal compatible con MySQL (que trata la barra invertida como escape) y SQLite.
$q = fn($v) => $v === null ? "NULL" : (is_int($v) || is_float($v) ? (string) $v : "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string) $v) . "'");
$insert = function (string $table, array $row) use ($q): string {
    return "INSERT INTO $table(" . implode(",", array_keys($row)) . ") VALUES(" . implode(",", array_map($q, array_values($row))) . ");";
};
$props = $pdo->prepare("SELECT * FROM properties WHERE organization_id=? AND deleted_at IS NULL ORDER BY created_at");
$props->execute([$from]);
$props = $props->fetchAll();
$out = ["-- Cartera exportada a la organización $to. Importar UNA vez, después de la cuenta.", "START TRANSACTION;"];
$mediaIds = [];
foreach ($props as $p) {
    $images = json_decode($p["images_json"], true);
    $p["images_json"] = json_encode(array_map(function ($url) use ($prefix, &$mediaIds) {
        if (preg_match('~/public/media/([0-9a-f-]{36})$~', $url, $m)) {
            $mediaIds[] = $m[1];
            return "$prefix/public/media/{$m[1]}";
        }
        return $url;
    }, $images), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $p["organization_id"] = $to;
    $p["version"] = (int) $p["version"];
    $p["beds"] = (int) $p["beds"];
    $p["baths"] = (int) $p["baths"];
    $p["price_minor"] = (int) $p["price_minor"];
    $out[] = $insert("properties", $p);
}
@mkdir("$root/export/media", 0700, true);
$copied = 0;
foreach (array_unique($mediaIds) as $id) {
    $m = $pdo->prepare("SELECT * FROM media WHERE id=? AND organization_id=?");
    $m->execute([$id, $from]);
    $m = $m->fetch();
    if (!$m) {
        fwrite(STDERR, "Falta la foto $id\n");
        exit(1);
    }
    foreach ([$m["storage_name"], str_replace(".webp", "-thumb.webp", $m["storage_name"])] as $file) {
        $src = "$root/backend/var/media/$file";
        if (is_file($src)) {
            copy($src, "$root/export/media/$file");
            $copied++;
        }
    }
    $m["organization_id"] = $to;
    foreach (["bytes", "width", "height"] as $k) {
        $m[$k] = (int) $m[$k];
    }
    $out[] = $insert("media", $m);
}
$now = gmdate("Y-m-d\TH:i:s\Z");
$out[] = "INSERT INTO administrative_changes(id,organization_id,reason,old_limit,new_limit,old_status,new_status,created_at,actor) SELECT " .
    $q(sprintf("%s-%s-4%s-a%s-%s", bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), substr(bin2hex(random_bytes(2)), 1), substr(bin2hex(random_bytes(2)), 1), bin2hex(random_bytes(6)))) .
    ",organization_id,'Carga inicial de cartera exportada',publication_limit,$limit,status,'active'," . $q($now) . ",'export' FROM subscriptions WHERE organization_id=" . $q($to) . ";";
$out[] = "UPDATE subscriptions SET publication_limit=$limit,status='active',updated_at=" . $q($now) . " WHERE organization_id=" . $q($to) . ";";
$out[] = "COMMIT;";
umask(077);
file_put_contents("$root/export/portfolio.sql", implode("\n", $out) . "\n");
printf("export/portfolio.sql: %d propiedades, %d fotos · export/media: %d archivos\n", count($props), count(array_unique($mediaIds)), $copied);
