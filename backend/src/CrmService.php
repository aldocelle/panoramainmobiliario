<?php
declare(strict_types=1);
namespace Panorama;
final class CrmService
{
    public function __construct(
        private Database $db,
        private Auth $auth,
        private array $config,
    ) {}
    public function consent(array $body): string
    {
        ApiError::require(
            $this->config["privacyVersion"] !== "",
            503,
            "PRIVACY_NOT_CONFIGURED",
            "La recepción de datos aún no está habilitada.",
        );
        ApiError::require(
            ($body["consent"] ?? false) === true &&
                ($body["consentVersion"] ?? "") ===
                    $this->config["privacyVersion"],
            422,
            "CONSENT_REQUIRED",
            "Revisa y confirma el aviso de datos vigente.",
        );
        ApiError::require(
            ($body["website"] ?? "") === "",
            422,
            "INVALID_REQUEST",
            "Solicitud inválida.",
        );
        return $this->config["privacyVersion"];
    }
    public function createLead(Request $r, string $property): Response
    {
        Validator::keys($r->body, [
            "name",
            "email",
            "phone",
            "note",
            "consent",
            "consentVersion",
            "website",
        ]);
        $consent = $this->consent($r->body);
        $name = Validator::string($r->body, "name", 2, 100);
        $email = Validator::email($r->body);
        $phone = Validator::string($r->body, "phone", 0, 30, "");
        $note = Validator::string($r->body, "note", 3, 2000);
        $id = Support::uuid();
        $this->db->transaction(function () use (
            $r,
            $property,
            $consent,
            $name,
            $email,
            $phone,
            $note,
            $id,
        ) {
            $p = Support::record(
                $this->db->one(
                    "SELECT organization_id FROM properties WHERE id=? AND status='Publicada' AND deleted_at IS NULL",
                    [$property],
                ),
            );
            $this->db->lockOrganization($p["organization_id"]);
            Support::record(
                $this->db->one(
                    "SELECT p.id FROM properties p JOIN subscriptions s ON s.organization_id=p.organization_id WHERE p.id=? AND s.status IN ('pilot','active') AND p.status='Publicada' AND deleted_at IS NULL",
                    [$property],
                ),
            );
            $now = Support::now();
            $this->db->run(
                "INSERT INTO leads VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $id,
                    $p["organization_id"],
                    $property,
                    $name,
                    $email,
                    $phone,
                    $note,
                    "Nuevo",
                    $consent,
                    $now,
                    1,
                    $now,
                    $now,
                ],
            );
            Support::audit(
                $this->db,
                $r,
                "lead.received",
                "lead",
                $id,
                $p["organization_id"],
            );
        });
        return new Response(
            ["id" => $id, "received" => true, "notificationSent" => false],
            201,
        );
    }
    private function lead(Request $r, string $id): array
    {
        return Support::record(
            $this->db->one(
                "SELECT * FROM leads WHERE id=? AND organization_id=?",
                [$id, $r->auth["organizationId"]],
            ),
        );
    }
    public function leads(Request $r): Response
    {
        $a = $this->auth->require($r);
        [$page, $limit, $offset] = Validator::page($r->query);
        $items = $this->db->all(
            "SELECT * FROM leads WHERE organization_id=? ORDER BY created_at DESC,id LIMIT $limit OFFSET $offset",
            [$a["organizationId"]],
        );
        return new Response([
            "items" => $items,
            "page" => $page,
            "limit" => $limit,
        ]);
    }
    public function updateLead(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        Validator::keys($r->body, ["stage", "note", "version"]);
        $stage = Validator::enum($r->body, "stage", [
            "Nuevo",
            "Contactado",
            "Visita agendada",
            "En negociación",
            "Cerrado",
        ]);
        $note = Validator::string($r->body, "note", 0, 4000);
        $version = Validator::version($r->body);
        $this->db->transaction(function () use (
            $r,
            $a,
            $id,
            $stage,
            $note,
            $version,
        ) {
            $this->db->lockOrganization($a["organizationId"]);
            Support::version($this->lead($r, $id), $version);
            $this->db->run(
                "UPDATE leads SET stage=?,note=?,updated_at=?,version=version+1 WHERE id=? AND organization_id=?",
                [$stage, $note, Support::now(), $id, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "lead.updated", "lead", $id);
        });
        return new Response($this->lead($r, $id));
    }
    public function eraseLead(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        Validator::keys($r->body, ["version"]);
        $version = Validator::version($r->body);
        $this->db->transaction(function () use ($r, $a, $id, $version) {
            $this->db->lockOrganization($a["organizationId"]);
            Support::version($this->lead($r, $id), $version);
            $this->db->run(
                "UPDATE leads SET name='Contacto anonimizado',email='',phone='',note='',updated_at=?,version=version+1 WHERE id=? AND organization_id=?",
                [Support::now(), $id, $a["organizationId"]],
            );
            $this->db->run(
                "UPDATE visits SET note='',status=CASE WHEN status='scheduled' THEN 'cancelled' ELSE status END,updated_at=?,version=version+1 WHERE lead_id=? AND organization_id=?",
                [Support::now(), $id, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "lead.anonymized", "lead", $id);
        });
        return new Response(null, 204);
    }
    private function visitData(array $d, bool $update = false): array
    {
        Validator::keys(
            $d,
            array_merge(
                [
                    "propertyId",
                    "leadId",
                    "agentId",
                    "startsAt",
                    "endsAt",
                    "note",
                ],
                $update ? ["version", "status"] : [],
            ),
        );
        $start = Validator::instant($d, "startsAt");
        $end = Validator::instant($d, "endsAt");
        ApiError::require(
            $start > time() && $end > $start && $end - $start <= 8 * 3600,
            422,
            "INVALID_VISIT_TIME",
            "La visita debe ser futura y durar hasta ocho horas.",
        );
        $out = [
            "property_id" => Validator::id($d, "propertyId"),
            "lead_id" => Validator::id($d, "leadId"),
            "agent_id" => Validator::id($d, "agentId"),
            "starts_at" => $start,
            "ends_at" => $end,
            "note" => Validator::string($d, "note", 0, 2000, ""),
            "status" => $update
                ? Validator::enum($d, "status", [
                    "scheduled",
                    "cancelled",
                    "completed",
                ])
                : "scheduled",
        ];
        if ($update) {
            $out["version"] = Validator::version($d);
        }
        return $out;
    }
    private function checkVisit(Request $r, array $d, string $except = ""): void
    {
        $org = $r->auth["organizationId"];
        $lead = $this->lead($r, $d["lead_id"]);
        ApiError::require(
            $lead["email"] !== "",
            409,
            "LEAD_ANONYMIZED",
            "El contacto fue anonimizado.",
        );
        ApiError::require(
            $lead["property_id"] === $d["property_id"],
            422,
            "LEAD_PROPERTY_MISMATCH",
            "El interesado no corresponde a esa propiedad.",
        );
        Support::record(
            $this->db->one(
                "SELECT id FROM properties WHERE id=? AND organization_id=? AND deleted_at IS NULL",
                [$d["property_id"], $org],
            ),
        );
        $member = Support::record(
            $this->db->one(
                "SELECT role FROM memberships WHERE user_id=? AND organization_id=?",
                [$d["agent_id"], $org],
            ),
        );
        ApiError::require(
            in_array($member["role"], ["owner", "admin", "agent"], true),
            422,
            "INVALID_AGENT",
            "El responsable no puede gestionar visitas.",
        );
        if ($d["status"] !== "scheduled") {
            return;
        }
        $conflict = $this->db->one(
            "SELECT id FROM visits WHERE organization_id=? AND status='scheduled' AND id<>? AND starts_at<? AND ends_at>? AND (agent_id=? OR property_id=?) LIMIT 1",
            [
                $org,
                $except,
                $d["ends_at"],
                $d["starts_at"],
                $d["agent_id"],
                $d["property_id"],
            ],
        );
        ApiError::require(
            !$conflict,
            409,
            "VISIT_CONFLICT",
            "El profesional o la propiedad ya tienen una visita en ese período.",
        );
    }
    public function createVisit(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        $d = $this->visitData($r->body);
        $id = Support::uuid();
        $this->db->transaction(function () use ($r, $a, $d, $id) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->checkVisit($r, $d);
            $now = Support::now();
            $this->db->run(
                "INSERT INTO visits VALUES(?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $id,
                    $a["organizationId"],
                    $d["property_id"],
                    $d["lead_id"],
                    $d["agent_id"],
                    $d["starts_at"],
                    $d["ends_at"],
                    $d["note"],
                    "scheduled",
                    1,
                    $now,
                    $now,
                ],
            );
            $this->db->run(
                "UPDATE leads SET stage='Visita agendada',version=version+1,updated_at=? WHERE id=? AND organization_id=?",
                [$now, $d["lead_id"], $a["organizationId"]],
            );
            Support::audit($this->db, $r, "visit.scheduled", "visit", $id);
        });
        return new Response($this->getVisit($r, $id), 201);
    }
    private function getVisit(Request $r, string $id): array
    {
        return Support::record(
            $this->db->one(
                "SELECT * FROM visits WHERE id=? AND organization_id=?",
                [$id, $r->auth["organizationId"]],
            ),
        );
    }
    public function visits(Request $r): Response
    {
        $a = $this->auth->require($r);
        [$page, $limit, $offset] = Validator::page($r->query);
        return new Response([
            "items" => $this->db->all(
                "SELECT * FROM visits WHERE organization_id=? ORDER BY starts_at,id LIMIT $limit OFFSET $offset",
                [$a["organizationId"]],
            ),
            "page" => $page,
            "limit" => $limit,
            "timeFormat" => "UTC epoch seconds",
        ]);
    }
    public function updateVisit(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        $d = $this->visitData($r->body, true);
        $this->db->transaction(function () use ($r, $a, $d, $id) {
            $this->db->lockOrganization($a["organizationId"]);
            Support::version($this->getVisit($r, $id), $d["version"]);
            $this->checkVisit($r, $d, $id);
            unset($d["version"]);
            $d["updated_at"] = Support::now();
            $assign = implode(
                ",",
                array_map(fn($k) => $k . "=?", array_keys($d)),
            );
            $this->db->run(
                "UPDATE visits SET $assign,version=version+1 WHERE id=? AND organization_id=?",
                [...array_values($d), $id, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "visit.updated", "visit", $id);
        });
        return new Response($this->getVisit($r, $id));
    }
    public function cancelVisit(Request $r, string $id): Response
    {
        $a = $this->auth->require($r, ["owner", "admin", "agent"]);
        Validator::keys($r->body, ["version"]);
        $v = Validator::version($r->body);
        $this->db->transaction(function () use ($r, $a, $v, $id) {
            $this->db->lockOrganization($a["organizationId"]);
            Support::version($this->getVisit($r, $id), $v);
            $this->db->run(
                "UPDATE visits SET status='cancelled',version=version+1,updated_at=? WHERE id=? AND organization_id=?",
                [Support::now(), $id, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "visit.cancelled", "visit", $id);
        });
        return new Response($this->getVisit($r, $id));
    }
}
