<?php
declare(strict_types=1);
namespace Panorama;
final class Support
{
    public static function now(): string
    {
        return gmdate("Y-m-d\TH:i:s\Z");
    }
    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return substr($h, 0, 8) .
            "-" .
            substr($h, 8, 4) .
            "-" .
            substr($h, 12, 4) .
            "-" .
            substr($h, 16, 4) .
            "-" .
            substr($h, 20);
    }
    public static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");
    }
    public static function hash(string $value): string
    {
        return hash("sha256", $value);
    }
    public static function normalize(string $value): string
    {
        $text = mb_strtolower(trim($value), "UTF-8");
        return strtr($text, [
            "á" => "a",
            "é" => "e",
            "í" => "i",
            "ó" => "o",
            "ú" => "u",
            "ü" => "u",
            "ñ" => "n",
        ]);
    }
    public static function record(?array $record): array
    {
        if (!$record) {
            throw new ApiError(404, "NOT_FOUND", "Registro no encontrado.");
        }
        return $record;
    }
    public static function version(array $record, int $version): void
    {
        ApiError::require(
            (int) $record["version"] === $version,
            409,
            "VERSION_CONFLICT",
            "El registro cambió. Recarga antes de guardar.",
        );
    }
    public static function json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES,
        );
    }
    public static function audit(
        Database $db,
        Request $req,
        string $action,
        string $entity,
        string $id,
        ?string $org = null,
    ): void {
        $db->run(
            "INSERT INTO audit_events(id,organization_id,actor_id,action,entity_type,entity_id,request_id,created_at) VALUES(?,?,?,?,?,?,?,?)",
            [
                self::uuid(),
                $org ?? $req->auth["organizationId"],
                $req->auth["userId"] ?? null,
                $action,
                $entity,
                $id,
                $req->id,
                self::now(),
            ],
        );
    }
    public static function csv(mixed $value): string
    {
        $v = (string) ($value ?? "");
        if (preg_match("/^\s*[=+@-]/u", $v)) {
            $v = "'" . $v;
        }
        return '"' . str_replace('"', '""', $v) . '"';
    }
}
