<?php
declare(strict_types=1);
namespace Panorama;
final class FavoriteService
{
    public function __construct(private Database $db, private Auth $auth) {}
    public function list(Request $r): Response
    {
        $a = $this->auth->require($r);
        [$page, $limit, $offset] = Validator::page($r->query);
        return new Response([
            "items" => $this->db->all(
                "SELECT f.property_id,f.created_at,p.title,p.commune,p.status FROM favorites f JOIN properties p ON p.id=f.property_id JOIN subscriptions s ON s.organization_id=p.organization_id WHERE f.user_id=? AND p.deleted_at IS NULL AND p.status='Publicada' AND s.status IN ('pilot','active') ORDER BY f.created_at DESC LIMIT $limit OFFSET $offset",
                [$a["userId"]],
            ),
            "page" => $page,
            "limit" => $limit,
        ]);
    }
    public function add(Request $r, string $id): Response
    {
        $a = $this->auth->require($r);
        Validator::keys($r->body, []);
        $this->db->transaction(function () use ($a, $id) {
            Support::record(
                $this->db->one(
                    "SELECT p.id FROM properties p JOIN subscriptions s ON s.organization_id=p.organization_id WHERE p.id=? AND p.status='Publicada' AND p.deleted_at IS NULL AND s.status IN ('pilot','active')",
                    [$id],
                ),
            );
            $sql =
                $this->db->driver === "mysql"
                    ? "INSERT IGNORE INTO favorites VALUES(?,?,?)"
                    : "INSERT OR IGNORE INTO favorites VALUES(?,?,?)";
            $this->db->run($sql, [$a["userId"], $id, Support::now()]);
        });
        return new Response(["propertyId" => $id, "saved" => true]);
    }
    public function remove(Request $r, string $id): Response
    {
        $a = $this->auth->require($r);
        Validator::keys($r->body, []);
        $this->db->run(
            "DELETE FROM favorites WHERE user_id=? AND property_id=?",
            [$a["userId"], $id],
        );
        return new Response(null, 204);
    }
}
