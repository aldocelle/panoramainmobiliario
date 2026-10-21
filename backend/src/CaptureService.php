<?php
declare(strict_types=1);
namespace Panorama;
final class CaptureService
{
    public function __construct(
        private Database $db,
        private Auth $auth,
        private CrmService $crm,
    ) {}
    public function enroll(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        Validator::keys($r->body, [
            "regions",
            "communes",
            "operations",
            "types",
            "presentation",
            "contactEmail",
        ]);
        $regions = Validator::list(
            $r->body,
            "regions",
            1,
            16,
            fn($v) => Validator::enum(
                ["value" => $v],
                "value",
                Validator::REGIONS,
            ),
        );
        $communes = Validator::list(
            $r->body,
            "communes",
            1,
            346,
            fn($v) => Validator::string(["value" => $v], "value", 2, 100),
        );
        $operations = Validator::list(
            $r->body,
            "operations",
            1,
            2,
            fn($v) => Validator::enum(["value" => $v], "value", [
                "Venta",
                "Arriendo",
            ]),
        );
        $types = Validator::list(
            $r->body,
            "types",
            1,
            2,
            fn($v) => Validator::enum(["value" => $v], "value", [
                "Casa",
                "Departamento",
            ]),
        );
        $presentation = Validator::string($r->body, "presentation", 10, 1500);
        $email = Validator::email($r->body, "contactEmail");
        $this->db->transaction(function () use (
            $r,
            $a,
            $regions,
            $communes,
            $operations,
            $types,
            $presentation,
            $email,
        ) {
            $this->db->lockOrganization($a["organizationId"]);
            $org = Support::record(
                $this->db->one("SELECT kind FROM organizations WHERE id=?", [
                    $a["organizationId"],
                ]),
            );
            ApiError::require(
                $org["kind"] === "broker",
                409,
                "BROKER_REQUIRED",
                "El directorio es para organizaciones de corretaje.",
            );
            $old = $this->db->one(
                "SELECT joined_at FROM broker_profiles WHERE organization_id=?",
                [$a["organizationId"]],
            );
            $values = [
                1,
                Support::json($regions),
                Support::json($communes),
                Support::json($operations),
                Support::json($types),
                $presentation,
                $email,
                Support::now(),
                $a["organizationId"],
            ];
            if ($old) {
                $this->db->run(
                    "UPDATE broker_profiles SET active=?,regions_json=?,communes_json=?,operations_json=?,types_json=?,presentation=?,contact_email=?,updated_at=? WHERE organization_id=?",
                    $values,
                );
            } else {
                $this->db->run(
                    "INSERT INTO broker_profiles(organization_id,active,regions_json,communes_json,operations_json,types_json,presentation,contact_email,joined_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)",
                    [
                        $a["organizationId"],
                        1,
                        Support::json($regions),
                        Support::json($communes),
                        Support::json($operations),
                        Support::json($types),
                        $presentation,
                        $email,
                        Support::now(),
                        Support::now(),
                    ],
                );
            }
            Support::audit(
                $this->db,
                $r,
                "broker.enrolled",
                "organization",
                $a["organizationId"],
            );
        });
        return new Response([
            "active" => true,
            "identityVerification" => "not_implemented",
            "notificationSent" => false,
        ]);
    }
    public function withdraw(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        Validator::keys($r->body, []);
        $this->db->transaction(function () use ($r, $a) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->db->run(
                "UPDATE broker_profiles SET active=0,updated_at=? WHERE organization_id=?",
                [Support::now(), $a["organizationId"]],
            );
            $this->db->run(
                "UPDATE broker_requests SET status='cancelled',updated_at=? WHERE organization_id=? AND status='pending'",
                [Support::now(), $a["organizationId"]],
            );
            Support::audit(
                $this->db,
                $r,
                "broker.withdrawn",
                "organization",
                $a["organizationId"],
            );
        });
        return new Response(["active" => false, "newRequestsStopped" => true]);
    }
    public function candidates(Request $r): Response
    {
        $region = isset($r->query["region"])
            ? Validator::enum($r->query, "region", Validator::REGIONS)
            : null;
        $commune = isset($r->query["commune"])
            ? Validator::string($r->query, "commune", 2, 100)
            : null;
        $operation = isset($r->query["operation"])
            ? Validator::enum($r->query, "operation", ["Venta", "Arriendo"])
            : null;
        $type = isset($r->query["type"])
            ? Validator::enum($r->query, "type", ["Casa", "Departamento"])
            : null;
        [$page, $limit, $offset] = Validator::page($r->query);
        $rows = $this->db->all(
            "SELECT b.*,o.name,o.logo_url FROM broker_profiles b JOIN organizations o ON o.id=b.organization_id WHERE b.active=1 ORDER BY b.organization_id",
        );
        $out = [];
        foreach ($rows as $b) {
            if (!$this->matches($b, $region, $commune, $operation, $type)) {
                continue;
            }
            $out[] = [
                "id" => $b["organization_id"],
                "name" => $b["name"],
                "logo" => $b["logo_url"],
                "presentation" => $b["presentation"],
                "regions" => json_decode($b["regions_json"], true),
                "communes" => json_decode($b["communes_json"], true),
                "operations" => json_decode($b["operations_json"], true),
                "types" => json_decode($b["types_json"], true),
                "joinedAt" => $b["joined_at"],
                "coverage" => "self_declared",
                "identityVerified" => false,
            ];
        }
        return new Response([
            "items" => array_slice($out, $offset, $limit),
            "total" => count($out),
            "page" => $page,
            "limit" => $limit,
        ]);
    }
    private function matches(
        array $b,
        ?string $region,
        ?string $commune,
        ?string $operation,
        ?string $type,
    ): bool {
        if (
            $region &&
            !in_array($region, json_decode($b["regions_json"], true), true)
        ) {
            return false;
        }
        if (
            $commune &&
            !in_array(
                Support::normalize($commune),
                array_map(
                    [Support::class, "normalize"],
                    json_decode($b["communes_json"], true),
                ),
                true,
            )
        ) {
            return false;
        }
        return (!$operation ||
            in_array(
                $operation,
                json_decode($b["operations_json"], true),
                true,
            )) &&
            (!$type ||
                in_array($type, json_decode($b["types_json"], true), true));
    }
    public function createRequest(Request $r): Response
    {
        Validator::keys($r->body, [
            "name",
            "email",
            "phone",
            "region",
            "commune",
            "operation",
            "type",
            "note",
            "brokerIds",
            "consent",
            "consentVersion",
            "website",
        ]);
        $consent = $this->crm->consent($r->body);
        $name = Validator::string($r->body, "name", 2, 100);
        $email = Validator::email($r->body);
        $phone = Validator::string($r->body, "phone", 0, 30, "");
        $region = Validator::enum($r->body, "region", Validator::REGIONS);
        $commune = Validator::string($r->body, "commune", 2, 100);
        $operation = Validator::enum($r->body, "operation", [
            "Venta",
            "Arriendo",
        ]);
        $type = Validator::enum($r->body, "type", ["Casa", "Departamento"]);
        $note = Validator::string($r->body, "note", 0, 2000, "");
        $brokers = Validator::list(
            $r->body,
            "brokerIds",
            1,
            3,
            fn($v) => Validator::id(["value" => $v], "value"),
        );
        ApiError::require(
            count($brokers) === count(array_unique($brokers)),
            422,
            "DUPLICATE_BROKERS",
            "No repitas destinatarios.",
        );
        sort($brokers);
        $id = Support::uuid();
        $token = Support::token();
        $this->db->transaction(function () use (
            $r,
            $brokers,
            $name,
            $email,
            $phone,
            $region,
            $commune,
            $operation,
            $type,
            $note,
            $consent,
            $id,
            $token,
        ) {
            // Same locking order as withdrawal, all or nothing for selected recipients.
            foreach ($brokers as $org) {
                $this->db->lockOrganization($org);
                $b = Support::record(
                    $this->db->one(
                        "SELECT * FROM broker_profiles WHERE organization_id=? AND active=1",
                        [$org],
                    ),
                );
                ApiError::require(
                    $this->matches($b, $region, $commune, $operation, $type),
                    422,
                    "COVERAGE_MISMATCH",
                    "Un corredor seleccionado no atiende esta solicitud.",
                );
            }
            $now = Support::now();
            $this->db->run(
                "INSERT INTO owner_requests VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $id,
                    $name,
                    $email,
                    $phone,
                    $region,
                    $commune,
                    $operation,
                    $type,
                    $note,
                    $consent,
                    Support::hash($token),
                    $now,
                ],
            );
            foreach ($brokers as $org) {
                $invitation = Support::uuid();
                $this->db->run(
                    "INSERT INTO broker_requests VALUES(?,?,?,?,?,?)",
                    [$invitation, $id, $org, "pending", $now, $now],
                );
                Support::audit(
                    $this->db,
                    $r,
                    "owner_request.received",
                    "capture",
                    $invitation,
                    $org,
                );
            }
        });
        return new Response(
            [
                "id" => $id,
                "managementToken" => $token,
                "selectedBrokers" => $brokers,
                "notificationSent" => false,
                "message" =>
                    "Conserva el token para consultar o cancelar esta solicitud.",
            ],
            201,
        );
    }
    public function manage(
        Request $r,
        string $id,
        bool $cancel = false,
    ): Response {
        $token = $r->header("x-request-token");
        ApiError::require(
            strlen($token) >= 40 && strlen($token) <= 100,
            401,
            "TOKEN_REQUIRED",
            "Token de solicitud requerido.",
        );
        $owner = Support::record(
            $this->db->one(
                "SELECT id FROM owner_requests WHERE id=? AND manage_token_hash=?",
                [$id, Support::hash($token)],
            ),
        );
        if ($cancel) {
            Validator::keys($r->body, []);
            $this->db->transaction(function () use ($r, $id) {
                $rows = $this->db->all(
                    "SELECT organization_id FROM broker_requests WHERE owner_request_id=? ORDER BY organization_id",
                    [$id],
                );
                foreach ($rows as $row) {
                    $this->db->lockOrganization($row["organization_id"]);
                }
                $this->db->run(
                    "UPDATE broker_requests SET status='cancelled',updated_at=? WHERE owner_request_id=? AND status IN ('pending','accepted')",
                    [Support::now(), $id],
                );
                foreach ($rows as $row) {
                    Support::audit(
                        $this->db,
                        $r,
                        "owner_request.cancelled",
                        "capture",
                        $id,
                        $row["organization_id"],
                    );
                }
            });
        }
        return new Response([
            "id" => $owner["id"],
            "recipients" => $this->db->all(
                "SELECT organization_id,status,created_at,updated_at FROM broker_requests WHERE owner_request_id=? ORDER BY organization_id",
                [$id],
            ),
        ]);
    }
    public function inbox(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        [$page, $limit, $offset] = Validator::page($r->query);
        $rows = $this->db->all(
            "SELECT b.id,b.status,b.owner_request_id,b.created_at,o.name,o.email,o.phone,o.region,o.commune,o.operation,o.type,o.note FROM broker_requests b JOIN owner_requests o ON o.id=b.owner_request_id WHERE b.organization_id=? ORDER BY b.created_at DESC,b.id LIMIT $limit OFFSET $offset",
            [$a["organizationId"]],
        );
        // Contact is disclosed only after explicit acceptance. Owner selected each
        // recipient before submission; rejected/cancelled requests disclose none.
        foreach ($rows as &$row) {
            if ($row["status"] !== "accepted") {
                unset($row["name"], $row["email"], $row["phone"], $row["note"]);
            }
        }
        unset($row);
        return new Response([
            "items" => $rows,
            "page" => $page,
            "limit" => $limit,
        ]);
    }
    public function decision(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        Validator::keys($r->body, ["decision"]);
        $decision = Validator::enum($r->body, "decision", [
            "accepted",
            "rejected",
        ]);
        $this->db->transaction(function () use ($r, $a, $id, $decision) {
            $this->db->lockOrganization($a["organizationId"]);
            $b = Support::record(
                $this->db->one(
                    "SELECT * FROM broker_requests WHERE id=? AND organization_id=?",
                    [$id, $a["organizationId"]],
                ),
            );
            $profile = $this->db->one(
                "SELECT active FROM broker_profiles WHERE organization_id=?",
                [$a["organizationId"]],
            );
            ApiError::require(
                $b["status"] === "pending" &&
                    (int) ($profile["active"] ?? 0) === 1,
                409,
                "REQUEST_NOT_PENDING",
                "La solicitud ya fue resuelta o la participación está pausada.",
            );
            $this->db->run(
                "UPDATE broker_requests SET status=?,updated_at=? WHERE id=?",
                [$decision, Support::now(), $id],
            );
            Support::audit(
                $this->db,
                $r,
                "capture." . $decision,
                "capture",
                $id,
            );
        });
        return new Response(["id" => $id, "status" => $decision]);
    }
}
