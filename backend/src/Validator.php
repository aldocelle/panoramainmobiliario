<?php
declare(strict_types=1);
namespace Panorama;
final class Validator
{
    public const REGIONS = [
        "15",
        "01",
        "02",
        "03",
        "04",
        "05",
        "13",
        "06",
        "07",
        "16",
        "08",
        "09",
        "14",
        "10",
        "11",
        "12",
    ];
    public const TYPES = [
        "Departamento",
        "Casa",
        "Terreno",
        "Oficina",
        "Local comercial",
        "Parcela",
        "Bodega",
    ];
    public const FURNISHED = ["Sin amoblar", "Semiamoblado", "Amoblado"];
    public const PETS = ["Sí", "No", "Consultar"];
    public const STATUSES = [
        "Borrador",
        "Publicada",
        "Pausada",
        "Vendida",
        "Arrendada",
    ];
    public static function keys(array $data, array $allowed): void
    {
        $extra = array_diff(array_keys($data), $allowed);
        ApiError::require(
            !$extra,
            422,
            "VALIDATION_ERROR",
            "Campos no permitidos: " . implode(", ", $extra),
        );
    }
    public static function string(
        array $data,
        string $key,
        int $min,
        int $max,
        ?string $default = null,
    ): string {
        $value = $data[$key] ?? $default;
        ApiError::require(
            is_string($value),
            422,
            "VALIDATION_ERROR",
            "$key debe ser texto.",
        );
        $value = trim($value);
        ApiError::require(
            mb_check_encoding($value, "UTF-8") &&
                mb_strlen($value) >= $min &&
                mb_strlen($value) <= $max &&
                !str_contains($value, "\0"),
            422,
            "VALIDATION_ERROR",
            "$key debe tener entre $min y $max caracteres.",
        );
        return $value;
    }
    public static function email(array $data, string $key = "email"): string
    {
        $v = mb_strtolower(self::string($data, $key, 3, 254));
        ApiError::require(
            filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
            422,
            "VALIDATION_ERROR",
            "$key inválido.",
        );
        return $v;
    }
    public static function enum(
        array $data,
        string $key,
        array $values,
        ?string $default = null,
    ): string {
        $v = self::string($data, $key, 1, 100, $default);
        ApiError::require(
            in_array($v, $values, true),
            422,
            "VALIDATION_ERROR",
            "$key no permitido.",
        );
        return $v;
    }
    public static function number(
        array $data,
        string $key,
        float $min,
        float $max,
        bool $integer = false,
    ): int|float {
        $v = $data[$key] ?? null;
        ApiError::require(
            (is_int($v) || is_float($v)) &&
                is_finite((float) $v) &&
                $v >= $min &&
                $v <= $max &&
                (!$integer || floor($v) === $v || is_int($v)),
            422,
            "VALIDATION_ERROR",
            "$key fuera de rango o formato.",
        );
        return $integer ? (int) $v : $v;
    }
    public static function id(array $data, string $key): string
    {
        $v = self::string($data, $key, 36, 36);
        ApiError::require(
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $v,
            ) === 1,
            422,
            "VALIDATION_ERROR",
            "$key inválido.",
        );
        return $v;
    }
    public static function https(string $value): string
    {
        $parts = parse_url($value);
        ApiError::require(
            strlen($value) <= 2048 &&
                filter_var($value, FILTER_VALIDATE_URL) !== false &&
                ($parts["scheme"] ?? "") === "https" &&
                !isset($parts["user"]) &&
                !isset($parts["pass"]),
            422,
            "VALIDATION_ERROR",
            "La imagen debe usar una URL HTTPS sin credenciales.",
        );
        return $value;
    }
    public static function password(array $data): string
    {
        $p = $data["password"] ?? null;
        ApiError::require(
            is_string($p) &&
                strlen($p) >= 12 &&
                strlen($p) <= 72 &&
                !str_contains($p, "\0"),
            422,
            "VALIDATION_ERROR",
            "La contraseña debe tener entre 12 y 72 bytes.",
        );
        return $p;
    }
    public static function list(
        array $data,
        string $key,
        int $min,
        int $max,
        callable $validate,
    ): array {
        $v = $data[$key] ?? null;
        ApiError::require(
            is_array($v) &&
                array_is_list($v) &&
                count($v) >= $min &&
                count($v) <= $max,
            422,
            "VALIDATION_ERROR",
            "$key inválido.",
        );
        return array_map($validate, $v);
    }
    public static function version(array $data): int
    {
        return (int) self::number($data, "version", 1, PHP_INT_MAX, true);
    }
    public static function property(array $d, bool $update = false): array
    {
        self::keys(
            $d,
            array_merge(
                [
                    "title",
                    "type",
                    "operation",
                    "currency",
                    "price",
                    "region",
                    "commune",
                    "sector",
                    "privateAddress",
                    "area",
                    "beds",
                    "baths",
                    "description",
                    "images",
                    "status",
                    "details",
                ],
                $update ? ["version"] : [],
            ),
        );
        $currency = self::enum($d, "currency", ["UF", "CLP"]);
        $price = self::number($d, "price", 0.01, 1e12);
        ApiError::require(
            abs($price * 100 - round($price * 100)) < 0.001 &&
                ($currency !== "CLP" || floor($price) == $price),
            422,
            "VALIDATION_ERROR",
            "UF admite dos decimales y CLP pesos enteros.",
        );
        $images = self::list($d + ["images" => []], "images", 0, 20, function (
            $v,
        ) {
            $v = self::string(["url" => $v], "url", 1, 2048);
            if (
                preg_match(
                    '~^/[A-Za-z0-9/_-]+/public/media/[0-9a-f-]{36}$~D',
                    $v,
                )
            ) {
                return $v;
            }
            return self::https($v);
        });
        $out = [
            "title" => self::string($d, "title", 3, 120),
            "type" => self::enum($d, "type", self::TYPES),
            "operation" => self::enum($d, "operation", ["Venta", "Arriendo"]),
            "currency" => $currency,
            "price_minor" => (int) round($price * 100),
            "region" => self::enum($d, "region", self::REGIONS),
            "commune" => self::string($d, "commune", 2, 100),
            "sector" => self::string($d, "sector", 0, 150, ""),
            "private_address" => self::string($d, "privateAddress", 0, 250, ""),
            "area" => self::number($d, "area", 0.01, 1e9),
            "beds" => self::number($d, "beds", 0, 99, true),
            "baths" => self::number($d, "baths", 0, 99, true),
            "description" => self::string($d, "description", 0, 6000, ""),
            "images_json" => Support::json($images),
            "status" => self::enum($d, "status", self::STATUSES, "Borrador"),
            "details_json" => self::details($d),
        ];
        if ($update) {
            $out["version"] = self::version($d);
        }
        return $out;
    }
    /** Características opcionales de la ficha; null o ausente equivale a "sin dato". */
    public static function details(array $d): string
    {
        $in = $d["details"] ?? [];
        ApiError::require(
            is_array($in) && ($in === [] || !array_is_list($in)),
            422,
            "VALIDATION_ERROR",
            "details inválido.",
        );
        self::keys($in, [
            "parking",
            "storage",
            "usefulArea",
            "terraceArea",
            "commonExpenses",
            "floor",
            "yearBuilt",
            "furnished",
            "pets",
            "amenities",
        ]);
        $numbers = [
            "parking" => [0, 99, true],
            "storage" => [0, 99, true],
            "usefulArea" => [0.01, 1e9, false],
            "terraceArea" => [0.01, 1e9, false],
            "commonExpenses" => [0, 1e10, true],
            "floor" => [-10, 300, true],
            "yearBuilt" => [1800, (int) gmdate("Y") + 5, true],
        ];
        $out = [];
        foreach ($numbers as $key => [$min, $max, $integer]) {
            if (isset($in[$key])) {
                $out[$key] = self::number($in, $key, $min, $max, $integer);
            }
        }
        foreach (["furnished" => self::FURNISHED, "pets" => self::PETS] as $key => $values) {
            if (isset($in[$key])) {
                $out[$key] = self::enum($in, $key, $values);
            }
        }
        if (isset($in["amenities"])) {
            $out["amenities"] = array_values(
                array_unique(
                    self::list(
                        $in,
                        "amenities",
                        0,
                        40,
                        fn($v) => self::string(["amenity" => $v], "amenity", 1, 80),
                    ),
                ),
            );
        }
        return Support::json($out);
    }
    public static function instant(array $d, string $key): int
    {
        $v = self::string($d, $key, 20, 40);
        ApiError::require(
            preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/',
                $v,
            ) === 1,
            422,
            "VALIDATION_ERROR",
            "$key requiere fecha ISO con zona horaria.",
        );
        try {
            $date = new \DateTimeImmutable($v);
            $errors = \DateTimeImmutable::getLastErrors();
        } catch (\Exception) {
            throw new ApiError(422, "VALIDATION_ERROR", "Fecha inválida.");
        }
        ApiError::require(
            $errors === false ||
                (!$errors["warning_count"] && !$errors["error_count"]),
            422,
            "VALIDATION_ERROR",
            "Fecha inválida.",
        );
        return $date->getTimestamp();
    }
    public static function page(array $q): array
    {
        $p = filter_var($q["page"] ?? 1, FILTER_VALIDATE_INT);
        $l = filter_var($q["limit"] ?? 20, FILTER_VALIDATE_INT);
        ApiError::require(
            $p !== false &&
                $p >= 1 &&
                $p <= 10000 &&
                $l !== false &&
                $l >= 1 &&
                $l <= 100,
            422,
            "VALIDATION_ERROR",
            "Paginación inválida.",
        );
        return [$p, $l, ($p - 1) * $l];
    }
}
