<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
// Reoptimiza las fotos ya guardadas con los tamaños actuales (MediaService::MAX_SIDE / THUMB_SIDE)
// y genera las miniaturas que falten. Solo reemplaza una foto si la versión nueva pesa menos.
// Se puede ejecutar varias veces sin efectos adicionales.
use Panorama\{Config, Database, MediaService};
$config = Config::read();
$db = new Database($config);
$before = $after = $thumbs = 0;
foreach ($db->all("SELECT id,storage_name FROM media ORDER BY created_at") as $m) {
    $path = $config["mediaPath"] . "/" . $m["storage_name"];
    if (!is_file($path)) {
        echo "! falta el archivo de {$m["id"]}\n";
        continue;
    }
    $thumb = preg_replace('/\.webp$/', "-thumb.webp", $path);
    $image = @imagecreatefromwebp($path);
    if (!$image) {
        echo "! no se pudo leer {$m["id"]}\n";
        continue;
    }
    $tmp = $path . ".tmp";
    $tmpThumb = $thumb . ".tmp";
    MediaService::writeOptimized($image, $tmp, $tmpThumb);
    $old = filesize($path);
    $before += $old;
    if (filesize($tmp) < $old) {
        rename($tmp, $path);
    } else {
        unlink($tmp);
    }
    if (!is_file($thumb)) {
        $thumbs++;
    }
    rename($tmpThumb, $thumb);
    clearstatcache();
    $size = getimagesize($path);
    $after += filesize($path);
    $db->run("UPDATE media SET bytes=?,width=?,height=? WHERE id=?", [filesize($path), $size[0], $size[1], $m["id"]]);
}
printf("Fotos: %.1f MB → %.1f MB. Miniaturas nuevas: %d.\n", $before / 1048576, $after / 1048576, $thumbs);
