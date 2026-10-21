<?php
declare(strict_types=1);
namespace Panorama;
final class Request
{
    public ?array $auth = null;
    public readonly string $id;
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $cookies = [],
        public readonly array $body = [],
        public readonly string $ip = "127.0.0.1",
    ) {
        $this->id = Support::uuid();
    }
    public function header(string $key): string
    {
        return $this->headers[strtolower($key)] ?? "";
    }
    public static function globals(): self
    {
        $headers = [];
        foreach (getallheaders() as $k => $v) {
            $headers[strtolower($k)] = $v;
        }
        $method = $_SERVER["REQUEST_METHOD"];
        $body = [];
        if (in_array($method, ["POST", "PUT", "PATCH", "DELETE"], true)) {
            $maxBytes =
                $method === "POST" &&
                str_ends_with(
                    parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?: "",
                    "/media",
                )
                    ? 5700000
                    : 131072;
            ApiError::require(
                (int) ($_SERVER["CONTENT_LENGTH"] ?? 0) <= $maxBytes,
                413,
                "BODY_TOO_LARGE",
                "Solicitud demasiado grande.",
            );
            ApiError::require(
                str_starts_with(
                    strtolower($headers["content-type"] ?? ""),
                    "application/json",
                ),
                415,
                "JSON_REQUIRED",
                "Utiliza application/json.",
            );
            $raw = file_get_contents(
                "php://input",
                false,
                null,
                0,
                $maxBytes + 1,
            );
            ApiError::require(
                strlen($raw) <= $maxBytes,
                413,
                "BODY_TOO_LARGE",
                "Solicitud demasiado grande.",
            );
            try {
                $decoded = json_decode(
                    $raw ?: "{}",
                    false,
                    64,
                    JSON_THROW_ON_ERROR,
                );
            } catch (\JsonException) {
                throw new ApiError(400, "INVALID_JSON", "JSON inválido.");
            }
            ApiError::require(
                is_object($decoded),
                400,
                "INVALID_JSON",
                "El cuerpo debe ser un objeto JSON.",
            );
            $body = json_decode($raw ?: "{}", true, 64, JSON_THROW_ON_ERROR);
        }
        return new self(
            $method,
            parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?: "/",
            $headers,
            $_GET,
            $_COOKIE,
            $body,
            $_SERVER["REMOTE_ADDR"] ?? "unknown",
        );
    }
}
