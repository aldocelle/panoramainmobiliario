<?php
declare(strict_types=1);
namespace Panorama;
final class Config
{
    public static function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (
            file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            as $line
        ) {
            $line = trim($line);
            if ($line === "" || $line[0] === "#" || !str_contains($line, "=")) {
                continue;
            }
            [$key, $value] = explode("=", $line, 2);
            $key = trim($key);
            if (
                !preg_match('/^[A-Z][A-Z0-9_]*$/', $key) ||
                getenv($key) !== false
            ) {
                continue;
            }
            putenv($key . "=" . trim(trim($value), "\"'"));
        }
    }
    public static function read(): array
    {
        $production = getenv("APP_ENV") === "production";
        $driver = getenv("DB_DRIVER") ?: "mysql";
        $key = getenv("APP_KEY") ?: "development-only-key-not-for-production";
        $origins = array_values(
            array_filter(
                array_map(
                    "trim",
                    explode(
                        ",",
                        getenv("ALLOWED_ORIGINS") ?: "http://localhost:5173",
                    ),
                ),
            ),
        );
        $secure = getenv("COOKIE_SECURE") === "true";
        if (!in_array($driver, ["mysql", "sqlite"], true)) {
            throw new \RuntimeException("DB_DRIVER inválido.");
        }
        if (
            $production &&
            ($driver !== "mysql" ||
                !$secure ||
                strlen($key) < 32 ||
                str_contains($key, "replace-") ||
                str_contains($key, "development-"))
        ) {
            throw new \RuntimeException(
                "Configura MySQL, APP_KEY único y cookies seguras antes de producción.",
            );
        }
        foreach ($origins as $origin) {
            $parts = parse_url($origin);
            if (
                !$parts ||
                !isset($parts["scheme"], $parts["host"]) ||
                (isset($parts["path"]) ||
                    isset($parts["user"]) ||
                    isset($parts["pass"]) ||
                    isset($parts["query"]) ||
                    isset($parts["fragment"])) ||
                str_contains($origin, "*") ||
                ($production && $parts["scheme"] !== "https")
            ) {
                throw new \RuntimeException(
                    "Usa orígenes exactos y HTTPS en producción.",
                );
            }
        }
        $mail = getenv("MAIL_TRANSPORT") ?: "disabled";
        if (!in_array($mail, ["disabled", "database", "native"], true)) {
            throw new \RuntimeException("MAIL_TRANSPORT inválido.");
        }
        $webBase = rtrim(getenv("WEB_BASE_URL") ?: "", "/");
        if (
            $webBase !== "" &&
            (!filter_var($webBase, FILTER_VALIDATE_URL) ||
                ($production && !str_starts_with($webBase, "https://")) ||
                str_contains($webBase, "#") ||
                str_contains($webBase, "?"))
        ) {
            throw new \RuntimeException("WEB_BASE_URL inválida.");
        }
        if ($production && $mail === "database") {
            throw new \RuntimeException(
                "El transporte database es solo para pruebas.",
            );
        }
        $hours = filter_var(
            getenv("SESSION_HOURS") ?: "12",
            FILTER_VALIDATE_INT,
        );
        if ($hours === false || $hours < 1 || $hours > 168) {
            throw new \RuntimeException("SESSION_HOURS inválido.");
        }
        return [
            "mailTransport" => $mail,
            "webBase" => $webBase,
            "mailFrom" => getenv("MAIL_FROM") ?: "",
            "mediaPath" => __DIR__ . "/../var/media",
            "production" => $production,
            "driver" => $driver,
            "key" => $key,
            "origins" => $origins,
            "secure" => $secure,
            "prefix" => rtrim(getenv("API_PREFIX") ?: "/api/v1", "/"),
            "sessionHours" => $hours,
            "registration" => getenv("ALLOW_PUBLIC_REGISTRATION") === "true",
            "privacyVersion" => getenv("PRIVACY_NOTICE_VERSION") ?: "",
            "dbPath" =>
                __DIR__ .
                "/../" .
                (getenv("DB_PATH") ?: "var/development.sqlite"),
            "host" => getenv("DB_HOST") ?: "127.0.0.1",
            "port" => getenv("DB_PORT") ?: "3306",
            "database" => getenv("DB_DATABASE") ?: "panorama",
            "username" => getenv("DB_USERNAME") ?: "panorama",
            "password" => getenv("DB_PASSWORD") ?: "",
        ];
    }
}
