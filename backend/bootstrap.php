<?php
declare(strict_types=1);
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, "Panorama\\")) {
        return;
    }
    $path =
        __DIR__ . "/src/" . str_replace("\\", "/", substr($class, 9)) . ".php";
    if (is_file($path)) {
        require $path;
    }
});
Panorama\Config::loadEnv(__DIR__ . "/.env");
date_default_timezone_set("UTC");
