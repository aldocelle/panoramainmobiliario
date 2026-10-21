<?php
declare(strict_types=1);
namespace Panorama;
use PDO;
final class Database
{
    public readonly PDO $pdo;
    public readonly string $driver;
    public function __construct(array $config)
    {
        $this->driver = $config["driver"];
        if ($this->driver === "sqlite") {
            $path = $config["dbPath"];
            if ($path !== ":memory:" && !is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            $this->pdo = new PDO("sqlite:" . $path);
            $this->pdo->exec(
                "PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;",
            );
        } else {
            $this->pdo = new PDO(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
                    $config["host"],
                    $config["port"],
                    $config["database"],
                ),
                $config["username"],
                $config["password"],
                [PDO::ATTR_EMULATE_PREPARES => false],
            );
            $this->pdo->exec("SET time_zone = '+00:00'");
            $this->pdo->exec(
                "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED",
            );
            $this->pdo->exec("SET default_storage_engine=InnoDB");
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC,
        );
    }
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        return $s;
    }
    public function one(string $sql, array $params = []): ?array
    {
        return $this->run($sql, $params)->fetch() ?: null;
    }
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }
    public function transaction(callable $callback): mixed
    {
        if ($this->driver === "sqlite") {
            $this->pdo->exec("BEGIN IMMEDIATE");
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $callback();
            if ($this->driver === "sqlite") {
                $this->pdo->exec("COMMIT");
            } else {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($this->driver === "sqlite") {
                $this->pdo->exec("ROLLBACK");
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    public function lockOrganization(string $id): void
    {
        $row = $this->one(
            "SELECT id FROM organizations WHERE id=?" .
                ($this->driver === "mysql" ? " FOR UPDATE" : ""),
            [$id],
        );
        ApiError::require(
            $row !== null,
            404,
            "NOT_FOUND",
            "Organización no encontrada.",
        );
    }
    public function migrate(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(160) PRIMARY KEY, applied_at VARCHAR(32) NOT NULL)",
        );
        if ($this->driver === "mysql") {
            ApiError::require(
                (int) $this->one(
                    "SELECT GET_LOCK('panorama_schema_migrations',10) AS acquired",
                )["acquired"] === 1,
                503,
                "MIGRATION_LOCK",
                "No se pudo bloquear la migración.",
            );
        }
        try {
            foreach (glob(__DIR__ . "/../database/migrations/*.sql") as $path) {
                $name = basename($path);
                if (
                    $this->one(
                        "SELECT name FROM schema_migrations WHERE name=?",
                        [$name],
                    )
                ) {
                    continue;
                }
                $execute = function () use ($path, $name) {
                    foreach (explode(";", file_get_contents($path)) as $sql) {
                        if (trim($sql) !== "") {
                            $this->pdo->exec($sql);
                        }
                    }
                    $this->run("INSERT INTO schema_migrations VALUES(?,?)", [
                        $name,
                        Support::now(),
                    ]);
                };
                // MySQL DDL hace commit implícito. Migraciones iniciales se ejecutan
                // antes de servir tráfico; si fallan no se marca la versión aplicada.
                if ($this->driver === "sqlite") {
                    $this->transaction($execute);
                } else {
                    $execute();
                }
            }
        } finally {
            if ($this->driver === "mysql") {
                $this->one("SELECT RELEASE_LOCK('panorama_schema_migrations')");
            }
        }
    }
}
