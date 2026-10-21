<?php
declare(strict_types=1);
namespace Panorama;
/**
 * Superadmin de plataforma: identidad, sesión y cookie propias, separadas de las cuentas de
 * corredoras. Una sesión de corredora nunca da acceso aquí y una de plataforma no opera carteras.
 */
final class PlatformService
{
    public const COOKIE = "panorama_platform";
    public const STATUSES = ["pilot", "active", "suspended", "expired"];
    public function __construct(
        private Database $db,
        private Auth $auth,
        private array $config,
    ) {}
    private function cookie(string $value, int $expires): array
    {
        return [
            "name" => self::COOKIE,
            "value" => $value,
            "options" => [
                "expires" => $expires,
                "path" => $this->config["prefix"] . "/platform",
                "secure" => $this->config["secure"],
                "httponly" => true,
                "samesite" => "Strict",
            ],
        ];
    }
    public function attach(Request $r): void
    {
        $token = $r->cookies[self::COOKIE] ?? "";
        if (!is_string($token) || $token === "" || strlen($token) > 100) {
            return;
        }
        $row = $this->db->one(
            "SELECT s.token_hash,s.csrf_token,a.id,a.name,a.email FROM platform_sessions s JOIN platform_admins a ON a.id=s.admin_id WHERE s.token_hash=? AND s.expires_at>? AND a.active=1",
            [Support::hash($token), time()],
        );
        if ($row) {
            $r->platform = [
                "adminId" => $row["id"],
                "name" => $row["name"],
                "email" => $row["email"],
                "csrf" => $row["csrf_token"],
                "sessionHash" => $row["token_hash"],
            ];
        }
    }
    public function csrf(Request $r): void
    {
        if (
            $r->platform &&
            in_array($r->method, ["POST", "PUT", "PATCH", "DELETE"], true)
        ) {
            ApiError::require(
                hash_equals($r->platform["csrf"], $r->header("x-csrf-token")),
                403,
                "CSRF_INVALID",
                "Token CSRF inválido.",
            );
        }
    }
    private function require(Request $r): array
    {
        ApiError::require(
            $r->platform !== null,
            401,
            "PLATFORM_AUTH_REQUIRED",
            "Inicia sesión como superadmin.",
        );
        return $r->platform;
    }
    public function login(Request $r): Response
    {
        Validator::keys($r->body, ["email", "password"]);
        $email = Validator::email($r->body);
        $password = $r->body["password"] ?? null;
        ApiError::require(
            is_string($password) && strlen($password) >= 1 && strlen($password) <= 72,
            422,
            "VALIDATION_ERROR",
            "Contraseña inválida.",
        );
        $admin = $this->db->one(
            "SELECT * FROM platform_admins WHERE email=? AND active=1",
            [$email],
        );
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid = password_verify($password, $admin["password_hash"] ?? $dummy);
        ApiError::require(
            $admin !== null && $valid,
            401,
            "INVALID_CREDENTIALS",
            "Credenciales inválidas.",
        );
        if ($r->platform) {
            $this->db->run("DELETE FROM platform_sessions WHERE token_hash=?", [
                $r->platform["sessionHash"],
            ]);
        }
        $token = Support::token();
        $csrf = Support::token();
        // Sesiones más cortas que las de corredoras: es la cuenta con más privilegios.
        $expiry = time() + min(8, $this->config["sessionHours"]) * 3600;
        $this->db->run(
            "INSERT INTO platform_sessions VALUES(?,?,?,?,?)",
            [Support::hash($token), $admin["id"], $csrf, $expiry, Support::now()],
        );
        return new Response(
            [
                "admin" => ["id" => $admin["id"], "name" => $admin["name"], "email" => $admin["email"]],
                "csrfToken" => $csrf,
                "expiresAt" => gmdate("c", $expiry),
            ],
            200,
            [],
            $this->cookie($token, $expiry),
        );
    }
    public function session(Request $r): Response
    {
        $a = $this->require($r);
        return new Response([
            "admin" => ["id" => $a["adminId"], "name" => $a["name"], "email" => $a["email"]],
            "csrfToken" => $a["csrf"],
        ]);
    }
    public function logout(Request $r): Response
    {
        $a = $this->require($r);
        $this->db->run("DELETE FROM platform_sessions WHERE token_hash=?", [$a["sessionHash"]]);
        return new Response(null, 204, [], $this->cookie("", 1));
    }
    public function organizations(Request $r): Response
    {
        $this->require($r);
        $items = $this->db->all(
            "SELECT o.id,o.name,o.kind,o.created_at,s.plan_id,s.status,s.publication_limit,
                (SELECT COUNT(*) FROM properties p WHERE p.organization_id=o.id AND p.deleted_at IS NULL) properties,
                (SELECT COUNT(*) FROM properties p WHERE p.organization_id=o.id AND p.deleted_at IS NULL AND p.status='Publicada') published,
                (SELECT COUNT(*) FROM leads l WHERE l.organization_id=o.id) leads,
                (SELECT COUNT(*) FROM memberships m WHERE m.organization_id=o.id AND m.role!='disabled') members,
                (SELECT u.email FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.organization_id=o.id AND m.role='owner' ORDER BY m.created_at LIMIT 1) owner_email
             FROM organizations o JOIN subscriptions s ON s.organization_id=o.id
             ORDER BY o.created_at DESC,o.id",
        );
        return new Response([
            "items" => array_map(
                fn($o) => [
                    "id" => $o["id"],
                    "name" => $o["name"],
                    "kind" => $o["kind"],
                    "createdAt" => $o["created_at"],
                    "ownerEmail" => $o["owner_email"],
                    "subscription" => [
                        "planId" => $o["plan_id"],
                        "status" => $o["status"],
                        "limit" => (int) $o["publication_limit"],
                        "used" => (int) $o["published"],
                    ],
                    "counts" => [
                        "properties" => (int) $o["properties"],
                        "leads" => (int) $o["leads"],
                        "members" => (int) $o["members"],
                    ],
                ],
                $items,
            ),
        ]);
    }
    public function createOrganization(Request $r): Response
    {
        $a = $this->require($r);
        [$user, $org] = $this->auth->createAccount($r->body);
        $r->auth = ["userId" => null, "organizationId" => $org];
        Support::audit($this->db, $r, "platform.organization_created", "organization", $org, $org);
        return new Response(["organizationId" => $org, "userId" => $user, "createdBy" => $a["email"]], 201);
    }
    public function capacity(Request $r, string $id): Response
    {
        $a = $this->require($r);
        Validator::keys($r->body, ["limit", "status", "reason"]);
        $limit = Validator::number($r->body, "limit", 0, 100000, true);
        $status = Validator::enum($r->body, "status", self::STATUSES);
        $reason = Validator::string($r->body, "reason", 5, 300);
        Support::record($this->db->one("SELECT id FROM organizations WHERE id=?", [$id]));
        self::changeCapacity($this->db, $r, $id, $limit, $status, $reason, "platform:" . $a["email"]);
        return $this->organizations($r);
    }
    public function changes(Request $r, string $id): Response
    {
        $this->require($r);
        return new Response([
            "items" => $this->db->all(
                "SELECT reason,old_limit,new_limit,old_status,new_status,actor,created_at FROM administrative_changes WHERE organization_id=? ORDER BY created_at DESC,id LIMIT 50",
                [$id],
            ),
        ]);
    }
    /** Cambio de cupo/estado con bloqueo, motivo y auditoría. Compartido con bin/set-capacity.php. */
    public static function changeCapacity(
        Database $db,
        Request $r,
        string $org,
        int $limit,
        string $status,
        string $reason,
        string $actor,
    ): void {
        $db->transaction(function () use ($db, $r, $org, $limit, $status, $reason, $actor) {
            $db->lockOrganization($org);
            $before = Support::record(
                $db->one("SELECT * FROM subscriptions WHERE organization_id=?", [$org]),
            );
            $used = (int) $db->one(
                "SELECT COUNT(*) total FROM properties WHERE organization_id=? AND status='Publicada' AND deleted_at IS NULL",
                [$org],
            )["total"];
            ApiError::require(
                $limit >= $used,
                409,
                "CAPACITY_TOO_SMALL",
                "Pausa avisos antes de reducir el límite.",
            );
            $db->run(
                "UPDATE subscriptions SET publication_limit=?,status=?,updated_at=? WHERE organization_id=?",
                [$limit, $status, Support::now(), $org],
            );
            $db->run(
                "INSERT INTO administrative_changes(id,organization_id,reason,old_limit,new_limit,old_status,new_status,created_at,actor) VALUES(?,?,?,?,?,?,?,?,?)",
                [Support::uuid(), $org, $reason, $before["publication_limit"], $limit, $before["status"], $status, Support::now(), $actor],
            );
            $r->auth = ["userId" => null, "organizationId" => $org];
            Support::audit($db, $r, "subscription.capacity_changed", "organization", $org, $org);
        });
    }
}
