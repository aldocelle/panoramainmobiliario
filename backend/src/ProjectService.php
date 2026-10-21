<?php
declare(strict_types=1);
namespace Panorama;
final class ProjectService
{
    public function __construct(private Database $db, private Auth $auth) {}
    private function developer(Request $r, bool $write = false): array
    {
        $a = $this->auth->require(
            $r,
            $write
                ? ["owner", "admin", "agent"]
                : ["owner", "admin", "agent", "viewer"],
        );
        $o = Support::record(
            $this->db->one("SELECT kind FROM organizations WHERE id=?", [
                $a["organizationId"],
            ]),
        );
        ApiError::require(
            $o["kind"] === "developer",
            403,
            "DEVELOPER_REQUIRED",
            "Este módulo corresponde a inmobiliarias.",
        );
        return $a;
    }
    private function project(Request $r, string $id): array
    {
        return Support::record(
            $this->db->one(
                "SELECT * FROM projects WHERE id=? AND organization_id=?",
                [$id, $r->auth["organizationId"]],
            ),
        );
    }
    public function list(Request $r): Response
    {
        $a = $this->developer($r);
        [$page, $limit, $offset] = Validator::page($r->query);
        return new Response([
            "items" => $this->db->all(
                "SELECT * FROM projects WHERE organization_id=? ORDER BY created_at DESC,id LIMIT $limit OFFSET $offset",
                [$a["organizationId"]],
            ),
            "page" => $page,
            "limit" => $limit,
        ]);
    }
    private function data(array $d, bool $update = false): array
    {
        Validator::keys(
            $d,
            array_merge(
                ["name", "region", "commune", "description", "status"],
                $update ? ["version"] : [],
            ),
        );
        $v = [
            "name" => Validator::string($d, "name", 3, 150),
            "region" => Validator::enum($d, "region", Validator::REGIONS),
            "commune" => Validator::string($d, "commune", 2, 100),
            "description" => Validator::string($d, "description", 0, 6000, ""),
            "status" => Validator::enum(
                $d,
                "status",
                ["draft", "published", "paused", "closed"],
                "draft",
            ),
        ];
        if ($update) {
            $v["version"] = Validator::version($d);
        }
        return $v;
    }
    public function create(Request $r): Response
    {
        $a = $this->developer($r, true);
        $d = $this->data($r->body);
        $id = Support::uuid();
        $now = Support::now();
        $this->db->transaction(function () use ($r, $a, $d, $id, $now) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->db->run("INSERT INTO projects VALUES(?,?,?,?,?,?,?,?,?,?)", [
                $id,
                $a["organizationId"],
                $d["name"],
                $d["region"],
                $d["commune"],
                $d["description"],
                $d["status"],
                1,
                $now,
                $now,
            ]);
            Support::audit($this->db, $r, "project.created", "project", $id);
        });
        return new Response($this->project($r, $id), 201);
    }
    public function update(Request $r, string $id): Response
    {
        $a = $this->developer($r, true);
        $d = $this->data($r->body, true);
        $this->db->transaction(function () use ($r, $a, $d, $id) {
            $this->db->lockOrganization($a["organizationId"]);
            Support::version($this->project($r, $id), $d["version"]);
            $this->db->run(
                "UPDATE projects SET name=?,region=?,commune=?,description=?,status=?,version=version+1,updated_at=? WHERE id=? AND organization_id=?",
                [
                    $d["name"],
                    $d["region"],
                    $d["commune"],
                    $d["description"],
                    $d["status"],
                    Support::now(),
                    $id,
                    $a["organizationId"],
                ],
            );
            Support::audit($this->db, $r, "project.updated", "project", $id);
        });
        return new Response($this->project($r, $id));
    }
    public function units(Request $r, string $project): Response
    {
        $a = $this->developer($r);
        $this->project($r, $project);
        [$page, $limit, $offset] = Validator::page($r->query);
        return new Response([
            "items" => $this->db->all(
                "SELECT * FROM units WHERE project_id=? AND organization_id=? ORDER BY code,id LIMIT $limit OFFSET $offset",
                [$project, $a["organizationId"]],
            ),
            "page" => $page,
            "limit" => $limit,
        ]);
    }
    private function unitData(array $d, bool $update = false): array
    {
        Validator::keys(
            $d,
            array_merge(
                [
                    "code",
                    "type",
                    "currency",
                    "price",
                    "area",
                    "beds",
                    "baths",
                    "status",
                ],
                $update ? ["version"] : [],
            ),
        );
        $currency = Validator::enum($d, "currency", ["UF", "CLP"]);
        $price = Validator::number($d, "price", 0.01, 1e12);
        ApiError::require(
            abs($price * 100 - round($price * 100)) < 0.001 &&
                ($currency !== "CLP" || floor($price) == $price),
            422,
            "VALIDATION_ERROR",
            "Precio inválido.",
        );
        $v = [
            "code" => Validator::string($d, "code", 1, 60),
            "type" => Validator::enum($d, "type", Validator::TYPES),
            "currency" => $currency,
            "price_minor" => (int) round($price * 100),
            "area" => Validator::number($d, "area", 0.01, 1e9),
            "beds" => Validator::number($d, "beds", 0, 99, true),
            "baths" => Validator::number($d, "baths", 0, 99, true),
            "status" => Validator::enum(
                $d,
                "status",
                ["available", "reserved", "sold"],
                "available",
            ),
        ];
        if ($update) {
            $v["version"] = Validator::version($d);
        }
        return $v;
    }
    public function createUnit(Request $r, string $project): Response
    {
        $a = $this->developer($r, true);
        $d = $this->unitData($r->body);
        $id = Support::uuid();
        $now = Support::now();
        $this->db->transaction(function () use (
            $r,
            $a,
            $d,
            $project,
            $id,
            $now,
        ) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->project($r, $project);
            $this->db->run(
                "INSERT INTO units VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $id,
                    $a["organizationId"],
                    $project,
                    $d["code"],
                    $d["type"],
                    $d["currency"],
                    $d["price_minor"],
                    $d["area"],
                    $d["beds"],
                    $d["baths"],
                    $d["status"],
                    1,
                    $now,
                    $now,
                ],
            );
            Support::audit($this->db, $r, "unit.created", "unit", $id);
        });
        return new Response(
            Support::record(
                $this->db->one(
                    "SELECT * FROM units WHERE id=? AND organization_id=?",
                    [$id, $a["organizationId"]],
                ),
            ),
            201,
        );
    }
    public function updateUnit(
        Request $r,
        string $project,
        string $id,
    ): Response {
        $a = $this->developer($r, true);
        $d = $this->unitData($r->body, true);
        $this->db->transaction(function () use ($r, $a, $d, $project, $id) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->project($r, $project);
            $old = Support::record(
                $this->db->one(
                    "SELECT * FROM units WHERE id=? AND project_id=? AND organization_id=?",
                    [$id, $project, $a["organizationId"]],
                ),
            );
            Support::version($old, $d["version"]);
            unset($d["version"]);
            $d["updated_at"] = Support::now();
            $assign = implode(
                ",",
                array_map(fn($k) => $k . "=?", array_keys($d)),
            );
            $this->db->run(
                "UPDATE units SET $assign,version=version+1 WHERE id=? AND organization_id=?",
                [...array_values($d), $id, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "unit.updated", "unit", $id);
        });
        return new Response(
            $this->db->one(
                "SELECT * FROM units WHERE id=? AND organization_id=?",
                [$id, $a["organizationId"]],
            ),
        );
    }
}
