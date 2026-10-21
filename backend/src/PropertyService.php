<?php
declare(strict_types=1);
namespace Panorama;
final class PropertyService
{
    public function __construct(
        private Database $db,
        private Auth $auth,
        private array $config,
    ) {}
    public function owned(Request $r, string $id): array
    {
        return Support::record(
            $this->db->one(
                "SELECT * FROM properties WHERE id=? AND organization_id=? AND deleted_at IS NULL",
                [$id, $r->auth["organizationId"]],
            ),
        );
    }
    public function serialize(array $p, bool $public = false): array
    {
        $out = [
            "id" => $p["id"],
            "title" => $p["title"],
            "type" => $p["type"],
            "operation" => $p["operation"],
            "currency" => $p["currency"],
            "price" => (int) $p["price_minor"] / 100,
            "region" => $p["region"],
            "commune" => $p["commune"],
            "sector" => $p["sector"],
            "area" => (float) $p["area"],
            "beds" => (int) $p["beds"],
            "baths" => (int) $p["baths"],
            "description" => $p["description"],
            "images" => json_decode($p["images_json"], true),
            "status" => $p["status"],
            "details" => (object) (json_decode($p["details_json"] ?? "{}", true) ?: []),
            "version" => (int) $p["version"],
            "createdAt" => $p["created_at"],
            "updatedAt" => $p["updated_at"],
        ];
        if (!$public) {
            $out["privateAddress"] = $p["private_address"];
        }
        if (isset($p["brand_name"])) {
            $out["brand"] = [
                "name" => $p["brand_name"],
                "logo" => $p["brand_logo"],
            ];
        }
        return $out;
    }
    private function checkImages(array $d, string $org): void
    {
        foreach (json_decode($d["images_json"], true) as $url) {
            if (!str_starts_with($url, "/")) {
                continue;
            }
            $id = basename($url);
            ApiError::require(
                $url === $this->config["prefix"] . "/public/media/" . $id,
                422,
                "INVALID_IMAGE",
                "Ruta de imagen inválida.",
            );
            Support::record(
                $this->db->one(
                    "SELECT id FROM media WHERE id=? AND organization_id=?",
                    [$id, $org],
                ),
            );
        }
    }
    private function quota(string $org, string $except = ""): void
    {
        $sub = Support::record(
            $this->db->one(
                "SELECT * FROM subscriptions WHERE organization_id=?",
                [$org],
            ),
        );
        ApiError::require(
            in_array($sub["status"], ["pilot", "active"], true),
            409,
            "SUBSCRIPTION_INACTIVE",
            "La suscripción no permite publicar.",
        );
        $count = (int) $this->db->one(
            "SELECT COUNT(*) AS total FROM properties WHERE organization_id=? AND status='Publicada' AND deleted_at IS NULL AND id<>?",
            [$org, $except],
        )["total"];
        ApiError::require(
            $count < (int) $sub["publication_limit"],
            409,
            "PUBLICATION_LIMIT",
            "No hay cupos disponibles. Guarda un borrador o pausa un aviso.",
        );
    }
    public function create(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        $d = Validator::property($r->body);
        $id = Support::uuid();
        $now = Support::now();
        $this->db->transaction(function () use ($a, $d, $id, $now, $r) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->checkImages($d, $a["organizationId"]);
            if ($d["status"] === "Publicada") {
                $this->quota($a["organizationId"]);
            }
            $row = ["id" => $id, "organization_id" => $a["organizationId"]] +
                $d + [
                    "version" => 1,
                    "deleted_at" => null,
                    "created_at" => $now,
                    "updated_at" => $now,
                ];
            $keys = array_keys($row);
            $this->db->run(
                "INSERT INTO properties(" .
                    implode(",", $keys) .
                    ") VALUES(" .
                    implode(",", array_fill(0, count($keys), "?")) .
                    ")",
                array_values($row),
            );
            Support::audit($this->db, $r, "property.created", "property", $id);
        });
        return new Response($this->serialize($this->owned($r, $id)), 201);
    }
    public function update(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        $d = Validator::property($r->body, true);
        $this->db->transaction(function () use ($a, $d, $id, $r) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->checkImages($d, $a["organizationId"]);
            $old = $this->owned($r, $id);
            Support::version($old, $d["version"]);
            if (
                $d["status"] === "Publicada" &&
                $old["status"] !== "Publicada"
            ) {
                $this->quota($a["organizationId"], $id);
            }
            unset($d["version"]);
            $d["updated_at"] = Support::now();
            $assign = implode(
                ",",
                array_map(fn($k) => $k . "=?", array_keys($d)),
            );
            $this->db->run(
                "UPDATE properties SET $assign,version=version+1 WHERE id=? AND organization_id=?",
                [...array_values($d), $id, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "property.updated", "property", $id);
        });
        return new Response($this->serialize($this->owned($r, $id)));
    }
    public function remove(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        Validator::keys($r->body, ["version"]);
        $version = Validator::version($r->body);
        $this->db->transaction(function () use ($r, $id, $a, $version) {
            $this->db->lockOrganization($a["organizationId"]);
            Support::version($this->owned($r, $id), $version);
            $this->db->run(
                "UPDATE properties SET status='Pausada',deleted_at=?,updated_at=?,version=version+1 WHERE id=? AND organization_id=?",
                [Support::now(), Support::now(), $id, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "property.archived", "property", $id);
        });
        return new Response(null, 204);
    }
    public function get(Request $r, string $id, bool $public = false): Response
    {
        if (!$public) {
            $this->auth->require($r);
            return new Response($this->serialize($this->owned($r, $id)));
        }
        $p = Support::record(
            $this->db->one(
                "SELECT p.*,o.name brand_name,o.logo_url brand_logo FROM properties p JOIN organizations o ON o.id=p.organization_id JOIN subscriptions s ON s.organization_id=p.organization_id WHERE p.id=? AND p.status='Publicada' AND p.deleted_at IS NULL AND s.status IN ('pilot','active')",
                [$id],
            ),
        );
        return new Response($this->serialize($p, true));
    }
    public function listing(Request $r, bool $public = false): Response
    {
        if (!$public) {
            $this->auth->require($r);
        }
        [$page, $limit, $offset] = Validator::page($r->query);
        $where = ["p.deleted_at IS NULL"];
        $params = [];
        if ($public) {
            $where[] = "p.status='Publicada'";
            $where[] =
                "EXISTS(SELECT 1 FROM subscriptions sx WHERE sx.organization_id=p.organization_id AND sx.status IN ('pilot','active'))";
        } else {
            $where[] = "p.organization_id=?";
            $params[] = $r->auth["organizationId"];
        }
        foreach (
            [
                "region" => Validator::REGIONS,
                "operation" => ["Venta", "Arriendo"],
                "type" => Validator::TYPES,
                "status" => Validator::STATUSES,
            ]
            as $key => $values
        ) {
            if (isset($r->query[$key]) && $r->query[$key] !== "") {
                ApiError::require(
                    !$public || $key !== "status",
                    422,
                    "VALIDATION_ERROR",
                    "El portal público solo muestra avisos publicados.",
                );
                $where[] = "p.$key=?";
                $params[] = Validator::enum($r->query, $key, $values);
            }
        }
        foreach (["commune", "q"] as $field) {
            if (!isset($r->query[$field]) || $r->query[$field] === "") {
                continue;
            }
            $value = Validator::string($r->query, $field, 1, 100);
            $value = strtr($value, ["!" => "!!", "%" => "!%", "_" => "!_"]);
            if ($field === "commune") {
                $where[] = "p.commune LIKE ? ESCAPE '!'";
                $params[] = "%" . $value . "%";
            } else {
                $where[] =
                    "(p.title LIKE ? ESCAPE '!' OR p.commune LIKE ? ESCAPE '!')";
                $params[] = "%" . $value . "%";
                $params[] = "%" . $value . "%";
            }
        }
        $sql = implode(" AND ", $where);
        $total = (int) $this->db->one(
            "SELECT COUNT(*) total FROM properties p WHERE $sql",
            $params,
        )["total"];
        $rows = $this->db->all(
            "SELECT p.*,o.name brand_name,o.logo_url brand_logo FROM properties p JOIN organizations o ON o.id=p.organization_id JOIN subscriptions s ON s.organization_id=p.organization_id WHERE $sql ORDER BY p.created_at DESC,p.id LIMIT $limit OFFSET $offset",
            $params,
        );
        return new Response([
            "items" => array_map(
                fn($p) => $this->serialize($p, $public),
                $rows,
            ),
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
        ]);
    }
}
