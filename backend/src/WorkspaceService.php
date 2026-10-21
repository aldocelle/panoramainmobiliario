<?php
declare(strict_types=1);
namespace Panorama;
final class WorkspaceService
{
    public function __construct(private Database $db, private Auth $auth) {}
    public function get(Request $r): Response
    {
        $a = $this->auth->require($r);
        return new Response(
            Support::record(
                $this->db->one(
                    "SELECT id,name,logo_url,kind,created_at FROM organizations WHERE id=?",
                    [$a["organizationId"]],
                ),
            ),
        );
    }
    public function update(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        Validator::keys($r->body, ["name", "logo"]);
        $name = Validator::string($r->body, "name", 2, 150);
        $logo = Validator::string($r->body, "logo", 0, 2048, "");
        if ($logo !== "") {
            Validator::https($logo);
        }
        $this->db->transaction(function () use ($r, $a, $name, $logo) {
            $this->db->run(
                "UPDATE organizations SET name=?,logo_url=? WHERE id=?",
                [$name, $logo, $a["organizationId"]],
            );
            Support::audit(
                $this->db,
                $r,
                "organization.updated",
                "organization",
                $a["organizationId"],
            );
        });
        return $this->get($r);
    }
    public function subscription(Request $r): Response
    {
        $a = $this->auth->require($r);
        $row = Support::record(
            $this->db->one(
                "SELECT * FROM subscriptions WHERE organization_id=?",
                [$a["organizationId"]],
            ),
        );
        $used = (int) $this->db->one(
            "SELECT COUNT(*) total FROM properties WHERE organization_id=? AND deleted_at IS NULL AND status='Publicada'",
            [$a["organizationId"]],
        )["total"];
        return new Response([
            "planId" => $row["plan_id"],
            "status" => $row["status"],
            "limit" => (int) $row["publication_limit"],
            "used" => $used,
            "remaining" => max(0, (int) $row["publication_limit"] - $used),
            "billingEnabled" => false,
        ]);
    }
    public function members(Request $r): Response
    {
        $a = $this->auth->require($r);
        return new Response([
            "items" => $this->db->all(
                "SELECT u.id,u.name,u.email,m.role FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.organization_id=? ORDER BY u.name",
                [$a["organizationId"]],
            ),
        ]);
    }
    public function invite(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner"]);
        Validator::keys($r->body, ["email", "role"]);
        $email = Validator::email($r->body);
        $role = Validator::enum($r->body, "role", ["admin", "agent", "viewer"]);
        $token = Support::token();
        $id = Support::uuid();
        $this->db->transaction(function () use (
            $r,
            $a,
            $email,
            $role,
            $token,
            $id,
        ) {
            $this->db->lockOrganization($a["organizationId"]);
            $this->db->run("INSERT INTO invitations VALUES(?,?,?,?,?,?,?,?)", [
                $id,
                $a["organizationId"],
                $email,
                $role,
                Support::hash($token),
                time() + 172800,
                null,
                Support::now(),
            ]);
            Support::audit(
                $this->db,
                $r,
                "invitation.created",
                "invitation",
                $id,
            );
        });
        // Manual delivery only: no email is sent or falsely reported as delivered.
        return new Response(
            [
                "id" => $id,
                "token" => $token,
                "expiresInSeconds" => 172800,
                "delivery" => "manual",
                "emailSent" => false,
            ],
            201,
        );
    }
    public function accept(Request $r): Response
    {
        $a = $this->auth->require($r);
        Validator::keys($r->body, ["token"]);
        $token = Validator::string($r->body, "token", 40, 100);
        $user = $this->auth->user($a["userId"]);
        $result = $this->db->transaction(function () use (
            $r,
            $a,
            $token,
            $user,
        ) {
            $invite = Support::record(
                $this->db->one("SELECT * FROM invitations WHERE token_hash=?", [
                    Support::hash($token),
                ]),
            );
            $this->db->lockOrganization($invite["organization_id"]);
            $invite = Support::record(
                $this->db->one("SELECT * FROM invitations WHERE id=?", [
                    $invite["id"],
                ]),
            );
            ApiError::require(
                $invite["accepted_at"] === null &&
                    (int) $invite["expires_at"] > time() &&
                    hash_equals($invite["email"], $user["email"]),
                403,
                "INVITATION_INVALID",
                "Invitación inválida para esta cuenta.",
            );
            ApiError::require(
                !$this->db->one(
                    "SELECT role FROM memberships WHERE organization_id=? AND user_id=?",
                    [$invite["organization_id"], $a["userId"]],
                ),
                409,
                "MEMBER_EXISTS",
                "Ya perteneces a esta organización.",
            );
            $this->db->run("INSERT INTO memberships VALUES(?,?,?,?)", [
                $invite["organization_id"],
                $a["userId"],
                $invite["role"],
                Support::now(),
            ]);
            $this->db->run("UPDATE invitations SET accepted_at=? WHERE id=?", [
                Support::now(),
                $invite["id"],
            ]);
            Support::audit(
                $this->db,
                $r,
                "invitation.accepted",
                "invitation",
                $invite["id"],
                $invite["organization_id"],
            );
            return [
                "organizationId" => $invite["organization_id"],
                "role" => $invite["role"],
            ];
        });
        return new Response($result);
    }
    public function changeRole(Request $r, string $user): Response
    {
        $a = $this->auth->require($r, ["owner"]);
        Validator::keys($r->body, ["role"]);
        $role = Validator::enum($r->body, "role", ["admin", "agent", "viewer"]);
        $this->db->transaction(function () use ($r, $a, $user, $role) {
            $this->db->lockOrganization($a["organizationId"]);
            $old = Support::record(
                $this->db->one(
                    "SELECT role FROM memberships WHERE user_id=? AND organization_id=?",
                    [$user, $a["organizationId"]],
                ),
            );
            ApiError::require(
                $old["role"] !== "owner",
                409,
                "OWNER_PROTECTED",
                "No se puede cambiar al propietario por esta vía.",
            );
            $this->db->run(
                "UPDATE memberships SET role=? WHERE user_id=? AND organization_id=?",
                [$role, $user, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "member.role_changed", "user", $user);
        });
        return new Response(["id" => $user, "role" => $role]);
    }
    public function revokeMember(Request $r, string $user): Response
    {
        $a = $this->auth->require($r, ["owner"]);
        Validator::keys($r->body, []);
        $this->db->transaction(function () use ($r, $a, $user) {
            $this->db->lockOrganization($a["organizationId"]);
            $member = Support::record(
                $this->db->one(
                    "SELECT role FROM memberships WHERE user_id=? AND organization_id=?",
                    [$user, $a["organizationId"]],
                ),
            );
            ApiError::require(
                $member["role"] !== "owner",
                409,
                "OWNER_PROTECTED",
                "No puedes retirar al propietario.",
            );
            $visits = (int) $this->db->one(
                "SELECT COUNT(*) total FROM visits WHERE organization_id=? AND agent_id=? AND status='scheduled'",
                [$a["organizationId"], $user],
            )["total"];
            ApiError::require(
                $visits === 0,
                409,
                "AGENT_HAS_VISITS",
                "Reasigna o cancela las visitas antes de retirar el acceso.",
            );
            // Historical visits retain their agent foreign key; revoke access by
            // moving membership to a non-access role instead of deleting history.
            $this->db->run(
                "UPDATE memberships SET role='disabled' WHERE user_id=? AND organization_id=?",
                [$user, $a["organizationId"]],
            );
            $this->db->run(
                "DELETE FROM sessions WHERE user_id=? AND organization_id=?",
                [$user, $a["organizationId"]],
            );
            Support::audit($this->db, $r, "member.revoked", "user", $user);
        });
        return new Response(null, 204);
    }
    public function dashboard(Request $r): Response
    {
        $a = $this->auth->require($r);
        $org = $a["organizationId"];
        return new Response([
            "properties" => $this->db->all(
                "SELECT status,COUNT(*) total FROM properties WHERE organization_id=? AND deleted_at IS NULL GROUP BY status",
                [$org],
            ),
            "leads" => $this->db->all(
                "SELECT stage,COUNT(*) total FROM leads WHERE organization_id=? GROUP BY stage",
                [$org],
            ),
            "upcomingVisits" => (int) $this->db->one(
                "SELECT COUNT(*) total FROM visits WHERE organization_id=? AND status='scheduled' AND starts_at>?",
                [$org, time()],
            )["total"],
            "generatedAt" => Support::now(),
            "historicalAnalyticsAvailable" => false,
        ]);
    }
    public function export(Request $r, bool $csv = false): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        $org = $a["organizationId"];
        $properties = $this->db->all(
            "SELECT * FROM properties WHERE organization_id=? AND deleted_at IS NULL ORDER BY id",
            [$org],
        );
        Support::audit(
            $this->db,
            $r,
            $csv ? "report.exported" : "workspace.exported",
            "organization",
            $org,
        );
        if ($csv) {
            $rows = [
                [
                    "Código",
                    "Título",
                    "Comuna",
                    "Operación",
                    "Estado",
                    "Moneda",
                    "Precio",
                ],
            ];
            foreach ($properties as $p) {
                $rows[] = [
                    $p["id"],
                    $p["title"],
                    $p["commune"],
                    $p["operation"],
                    $p["status"],
                    $p["currency"],
                    (string) ((int) $p["price_minor"] / 100),
                ];
            }
            return new Response(
                null,
                200,
                [
                    "Content-Disposition" =>
                        'attachment; filename="panorama-publicaciones.csv"',
                ],
                null,
                "\xEF\xBB\xBF" .
                    implode(
                        "\r\n",
                        array_map(
                            fn($row) => implode(
                                ";",
                                array_map([Support::class, "csv"], $row),
                            ),
                            $rows,
                        ),
                    ),
            );
        }
        return new Response(
            [
                "version" => 1,
                "generatedAt" => Support::now(),
                "properties" => $properties,
                "leads" => $this->db->all(
                    "SELECT * FROM leads WHERE organization_id=?",
                    [$org],
                ),
                "visits" => $this->db->all(
                    "SELECT * FROM visits WHERE organization_id=?",
                    [$org],
                ),
            ],
            200,
            [
                "Content-Disposition" =>
                    'attachment; filename="panorama-respaldo.json"',
            ],
        );
    }
    public function audit(Request $r): Response
    {
        $a = $this->auth->require($r, ["owner", "admin"]);
        [$page, $limit, $offset] = Validator::page($r->query);
        return new Response([
            "items" => $this->db->all(
                "SELECT id,actor_id,action,entity_type,entity_id,request_id,created_at FROM audit_events WHERE organization_id=? ORDER BY created_at DESC,id LIMIT $limit OFFSET $offset",
                [$a["organizationId"]],
            ),
            "page" => $page,
            "limit" => $limit,
        ]);
    }
}
