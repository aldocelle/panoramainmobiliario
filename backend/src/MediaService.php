<?php
declare(strict_types=1);
namespace Panorama;
final class MediaService
{
    public function __construct(
        private Database $db,
        private Auth $auth,
        private array $config,
    ) {}
    public function upload(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        Validator::keys($r->body, ["contentBase64"]);
        ApiError::require(
            function_exists("imagecreatefromstring") &&
                function_exists("imagewebp"),
            503,
            "MEDIA_UNAVAILABLE",
            "El servidor requiere GD con WebP.",
        );
        $encoded = $r->body["contentBase64"] ?? null;
        ApiError::require(
            is_string($encoded) && strlen($encoded) <= 5600000,
            413,
            "IMAGE_TOO_LARGE",
            "La imagen admite hasta 4 MiB.",
        );
        $bytes = base64_decode($encoded, true);
        ApiError::require(
            $bytes !== false && strlen($bytes) > 0 && strlen($bytes) <= 4194304,
            422,
            "INVALID_IMAGE",
            "Imagen inválida.",
        );
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $size = @getimagesizefromstring($bytes);
        ApiError::require(
            in_array($mime, ["image/jpeg", "image/png", "image/webp"], true) &&
                $size !== false &&
                $size[0] > 0 &&
                $size[1] > 0 &&
                $size[0] <= 4000 &&
                $size[1] <= 4000 &&
                $size[0] * $size[1] <= 8000000,
            422,
            "INVALID_IMAGE",
            "Solo JPEG, PNG o WebP de hasta 8 megapíxeles y 4000 píxeles por lado.",
        );
        $image = @imagecreatefromstring($bytes);
        ApiError::require(
            $image !== false,
            422,
            "INVALID_IMAGE",
            "No se pudo decodificar la imagen.",
        );
        if ($mime === "image/jpeg") {
            $image = self::orient($image, $bytes);
        }
        $folder = $this->config["mediaPath"];
        if (!is_dir($folder)) {
            mkdir($folder, 0700, true);
        }
        $id = Support::uuid();
        $name = $id . ".webp";
        $path = $folder . "/" . $name;
        $thumb = $folder . "/" . $id . "-thumb.webp";
        // Re-encode rather than storing arbitrary original bytes or SVG content.
        try {
            ApiError::require(
                self::writeOptimized($image, $path, $thumb),
                500,
                "MEDIA_WRITE_FAILED",
                "No se pudo guardar la imagen.",
            );
            $size = getimagesize($path);
            $length = filesize($path);
            ApiError::require(
                $length !== false && $length <= 5242880,
                413,
                "IMAGE_TOO_LARGE",
                "La imagen procesada supera el límite.",
            );
            $this->db->transaction(function () use (
                $r,
                $a,
                $id,
                $name,
                $size,
                $length,
            ) {
                $this->db->lockOrganization($a["organizationId"]);
                $count = (int) $this->db->one(
                    "SELECT COUNT(*) total FROM media WHERE organization_id=?",
                    [$a["organizationId"]],
                )["total"];
                ApiError::require(
                    $count < 1000,
                    409,
                    "MEDIA_LIMIT",
                    "Se alcanzó el límite de 1000 archivos de la organización.",
                );
                $this->db->run("INSERT INTO media VALUES(?,?,?,?,?,?,?,?)", [
                    $id,
                    $a["organizationId"],
                    $name,
                    "image/webp",
                    $length,
                    $size[0],
                    $size[1],
                    Support::now(),
                ]);
                Support::audit($this->db, $r, "media.uploaded", "media", $id);
            });
        } catch (\Throwable $e) {
            foreach ([$path, $thumb] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            throw $e;
        }
        return new Response(
            [
                "id" => $id,
                "url" => $this->config["prefix"] . "/public/media/" . $id,
                "mime" => "image/webp",
                "bytes" => $length,
                "width" => $size[0],
                "height" => $size[1],
            ],
            201,
        );
    }
    // Tamaños de entrega: la foto completa para la ficha y una miniatura para tarjetas y galerías.
    public const MAX_SIDE = 1920;
    public const THUMB_SIDE = 640;
    public const QUALITY = 78;
    public const THUMB_QUALITY = 72;
    /** Aplica la orientación EXIF de la cámara, que se pierde al recodificar. */
    public static function orient(\GdImage $image, string $jpeg): \GdImage
    {
        if (!function_exists("exif_read_data")) {
            return $image;
        }
        $exif = @exif_read_data("data://image/jpeg;base64," . base64_encode($jpeg));
        $o = (int) ($exif["Orientation"] ?? 1);
        if (in_array($o, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        $angle = [3 => 180, 4 => 180, 5 => 270, 6 => 270, 7 => 90, 8 => 90][$o] ?? 0;
        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }
    private static function scale(\GdImage $image, int $max): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $max) {
            return $image;
        }
        $ratio = $max / max($w, $h);
        $out = imagecreatetruecolor(max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        return $out;
    }
    /** Escribe la foto (lado mayor ≤ 1920 px) y su miniatura (≤ 640 px) en WebP. */
    public static function writeOptimized(\GdImage $image, string $path, string $thumb): bool
    {
        imagepalettetotruecolor($image);
        imagesavealpha($image, true);
        $ok = imagewebp(self::scale($image, self::MAX_SIDE), $path, self::QUALITY) &&
            imagewebp(self::scale($image, self::THUMB_SIDE), $thumb, self::THUMB_QUALITY);
        foreach ([$path, $thumb] as $file) {
            if (is_file($file)) {
                chmod($file, 0600);
            }
        }
        return $ok;
    }
    public function serve(
        Request $r,
        string $id,
        bool $public = false,
    ): Response {
        $media = Support::record(
            $this->db->one("SELECT * FROM media WHERE id=?", [$id]),
        );
        if ($public) {
            $linked = $this->db->one(
                "SELECT p.id FROM properties p JOIN subscriptions s ON s.organization_id=p.organization_id WHERE p.organization_id=? AND p.status='Publicada' AND p.deleted_at IS NULL AND s.status IN ('pilot','active') AND p.images_json LIKE ? LIMIT 1",
                [$media["organization_id"], "%" . $id . "%"],
            );
            ApiError::require(
                $linked !== null,
                404,
                "NOT_FOUND",
                "Archivo no encontrado.",
            );
        } else {
            $a = $this->auth->require($r);
            ApiError::require(
                $media["organization_id"] === $a["organizationId"],
                404,
                "NOT_FOUND",
                "Archivo no encontrado.",
            );
        }
        $path = $this->config["mediaPath"] . "/" . $media["storage_name"];
        $thumb = preg_replace('/\.webp$/', "-thumb.webp", $path);
        if (($r->query["size"] ?? "") === "thumb" && is_file($thumb)) {
            $path = $thumb;
        }
        ApiError::require(
            is_file($path),
            404,
            "NOT_FOUND",
            "Archivo no encontrado.",
        );
        return new Response(
            null,
            200,
            [
                "Content-Type" => "image/webp",
                "Content-Disposition" => 'inline; filename="' . $id . '.webp"',
                // Cada id es inmutable: la foto pública se puede guardar en caché por un año.
                "Cache-Control" => $public ? "public, max-age=31536000, immutable" : "private, no-store",
            ],
            null,
            file_get_contents($path),
        );
    }
    public function remove(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        Validator::keys($r->body, []);
        $path = null;
        $this->db->transaction(function () use ($r, $a, $id, &$path) {
            $this->db->lockOrganization($a["organizationId"]);
            $m = Support::record(
                $this->db->one(
                    "SELECT * FROM media WHERE id=? AND organization_id=?",
                    [$id, $a["organizationId"]],
                ),
            );
            $linked = $this->db->one(
                "SELECT id FROM properties WHERE organization_id=? AND deleted_at IS NULL AND images_json LIKE ? LIMIT 1",
                [$a["organizationId"], "%" . $id . "%"],
            );
            ApiError::require(
                $linked === null,
                409,
                "MEDIA_IN_USE",
                "Retira la imagen de los avisos antes de eliminarla.",
            );
            $this->db->run("DELETE FROM media WHERE id=?", [$id]);
            $path = $this->config["mediaPath"] . "/" . $m["storage_name"];
            Support::audit($this->db, $r, "media.deleted", "media", $id);
        });
        foreach ($path ? [$path, preg_replace('/\.webp$/', "-thumb.webp", $path)] : [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return new Response(null, 204);
    }
}
