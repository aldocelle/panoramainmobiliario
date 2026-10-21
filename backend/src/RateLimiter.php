<?php
declare(strict_types=1);
namespace Panorama;
final class RateLimiter
{
    public function __construct(private Database $db, private array $config) {}
    public function hit(
        Request $r,
        string $group,
        int $limit,
        int $seconds = 900,
    ): void {
        $key = hash_hmac("sha256", $group . ":" . $r->ip, $this->config["key"]);
        $allowed = $this->db->transaction(function () use (
            $key,
            $limit,
            $seconds,
        ) {
            // MySQL uses an atomic increment/upsert so missing rows cannot race.
            if ($this->db->driver === "mysql") {
                $this->db->run(
                    "INSERT INTO rate_limits(bucket,hits,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE hits=IF(expires_at<=?,1,hits+1), expires_at=IF(expires_at<=?,VALUES(expires_at),expires_at)",
                    [$key, time() + $seconds, time(), time()],
                );
                $row = $this->db->one(
                    "SELECT hits FROM rate_limits WHERE bucket=?",
                    [$key],
                );
                return (int) $row["hits"] <= $limit;
            }
            $row = $this->db->one("SELECT * FROM rate_limits WHERE bucket=?", [
                $key,
            ]);
            if (!$row) {
                $this->db->run("INSERT INTO rate_limits VALUES(?,1,?)", [
                    $key,
                    time() + $seconds,
                ]);
                return true;
            }
            if ((int) $row["expires_at"] <= time()) {
                $this->db->run(
                    "UPDATE rate_limits SET hits=1,expires_at=? WHERE bucket=?",
                    [time() + $seconds, $key],
                );
                return true;
            }
            $this->db->run(
                "UPDATE rate_limits SET hits=hits+1 WHERE bucket=?",
                [$key],
            );
            return (int) $row["hits"] < $limit;
        });
        ApiError::require(
            $allowed,
            429,
            "RATE_LIMITED",
            "Demasiadas solicitudes. Inténtalo más tarde.",
        );
    }
}
