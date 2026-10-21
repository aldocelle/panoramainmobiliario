<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
// Restricted server-side administrative command, not exposed as an HTTP API.
try {
    $input = json_decode(
        stream_get_contents(STDIN),
        true,
        32,
        JSON_THROW_ON_ERROR,
    );
    Panorama\Validator::keys($input, [
        "organizationId",
        "limit",
        "status",
        "reason",
    ]);
    $org = Panorama\Validator::id($input, "organizationId");
    $limit = Panorama\Validator::number($input, "limit", 0, 100000, true);
    $status = Panorama\Validator::enum($input, "status", [
        "pilot",
        "active",
        "suspended",
        "expired",
    ]);
    $reason = Panorama\Validator::string($input, "reason", 5, 300);
    $db = new Panorama\Database(Panorama\Config::read());
    $db->transaction(function () use ($db, $org, $limit, $status, $reason) {
        $db->lockOrganization($org);
        $before = $db->one(
            "SELECT * FROM subscriptions WHERE organization_id=?",
            [$org],
        );
        $used = (int) $db->one(
            "SELECT COUNT(*) total FROM properties WHERE organization_id=? AND status='Publicada' AND deleted_at IS NULL",
            [$org],
        )["total"];
        Panorama\ApiError::require(
            $limit >= $used,
            409,
            "CAPACITY_TOO_SMALL",
            "Pausa avisos antes de reducir el límite.",
        );
        $db->run(
            "UPDATE subscriptions SET publication_limit=?,status=?,updated_at=? WHERE organization_id=?",
            [$limit, $status, Panorama\Support::now(), $org],
        );
        $db->run("INSERT INTO administrative_changes VALUES(?,?,?,?,?,?,?,?)", [
            Panorama\Support::uuid(),
            $org,
            $reason,
            $before["publication_limit"],
            $limit,
            $before["status"],
            $status,
            Panorama\Support::now(),
        ]);
        $request = new Panorama\Request("CLI", "capacity");
        Panorama\Support::audit(
            $db,
            $request,
            "subscription.capacity_changed",
            "organization",
            $org,
            $org,
        );
    });
    echo "Capacidad actualizada y motivo registrado.", PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
