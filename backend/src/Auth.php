<?php
declare(strict_types=1);
namespace Panorama;
final class Auth
{
    public const COOKIE = "panorama_session";
    public function __construct(private Database $db, private array $config) {}
    public function attach(Request $r): void
    {
        $token = $r->cookies[self::COOKIE] ?? "";
        if (!is_string($token) || strlen($token) > 100) {
            return;
        }
        if ($token === "") {
            return;
        }
        $row = $this->db->one(
            'SELECT s.*,m.role FROM sessions s JOIN memberships m ON m.user_id=s.user_id AND m.organization_id=s.organization_id WHERE s.token_hash=? AND s.expires_at>? AND m.role!=\'disabled\'',
            [Support::hash($token), time()],
        );
        if ($row) {
            $r->auth = [
                "userId" => $row["user_id"],
                "organizationId" => $row["organization_id"],
                "role" => $row["role"],
                "csrf" => $row["csrf_token"],
                "sessionHash" => $row["token_hash"],
            ];
        }
    }
    public function require(
        Request $r,
        array $roles = ["owner", "admin", "agent", "viewer"],
    ): array {
        ApiError::require(
            $r->auth !== null,
            401,
            "AUTH_REQUIRED",
            "Inicia sesión.",
        );
        ApiError::require(
            in_array($r->auth["role"], $roles, true),
            403,
            "FORBIDDEN",
            "No tienes permiso para esta acción.",
        );
        return $r->auth;
    }
    public function csrf(Request $r): void
    {
        if (
            $r->auth &&
            in_array($r->method, ["POST", "PUT", "PATCH", "DELETE"], true)
        ) {
            ApiError::require(
                hash_equals($r->auth["csrf"], $r->header("x-csrf-token")),
                403,
                "CSRF_INVALID",
                "Token CSRF inválido.",
            );
        }
    }
    private function session(string $userId, string $organizationId): Response
    {
        $token = Support::token();
        $csrf = Support::token();
        $expiry = time() + $this->config["sessionHours"] * 3600;
        $this->db->run(
            "INSERT INTO sessions(token_hash,user_id,organization_id,csrf_token,expires_at,created_at) VALUES(?,?,?,?,?,?)",
            [
                Support::hash($token),
                $userId,
                $organizationId,
                $csrf,
                $expiry,
                Support::now(),
            ],
        );
        return new Response(
            [
                "user" => $this->user($userId),
                "organizationId" => $organizationId,
                "csrfToken" => $csrf,
                "expiresAt" => gmdate("c", $expiry),
            ],
            200,
            [],
            [
                "name" => self::COOKIE,
                "value" => $token,
                "options" => [
                    "expires" => $expiry,
                    "path" => "/",
                    "secure" => $this->config["secure"],
                    "httponly" => true,
                    "samesite" => "Lax",
                ],
            ],
        );
    }
    public function user(string $id): array
    {
        return Support::record(
            $this->db->one(
                "SELECT id,name,email,created_at FROM users WHERE id=?",
                [$id],
            ),
        );
    }
    public function createAccount(array $d): array
    {
        Validator::keys($d, [
            "name",
            "email",
            "password",
            "organizationName",
            "kind",
        ]);
        $email = Validator::email($d);
        $name = Validator::string($d, "name", 2, 100);
        $organization = Validator::string($d, "organizationName", 2, 150);
        $kind = Validator::enum($d, "kind", ["broker", "developer"], "broker");
        // Expensive work before the database transaction, never while holding locks.
        $hash = password_hash(Validator::password($d), PASSWORD_DEFAULT);
        return $this->db->transaction(function () use (
            $email,
            $name,
            $organization,
            $kind,
            $hash,
        ) {
            ApiError::require(
                !$this->db->one("SELECT id FROM users WHERE email=?", [$email]),
                409,
                "ACCOUNT_EXISTS",
                "No se pudo crear la cuenta con esos datos.",
            );
            $userId = Support::uuid();
            $orgId = Support::uuid();
            $now = Support::now();
            $this->db->run("INSERT INTO users VALUES(?,?,?,?,?)", [
                $userId,
                $email,
                $name,
                $hash,
                $now,
            ]);
            $this->db->run("INSERT INTO organizations VALUES(?,?,?,?,?)", [
                $orgId,
                $organization,
                "",
                $kind,
                $now,
            ]);
            $this->db->run("INSERT INTO memberships VALUES(?,?,?,?)", [
                $orgId,
                $userId,
                "owner",
                $now,
            ]);
            $this->db->run("INSERT INTO subscriptions VALUES(?,?,?,?,?)", [
                $orgId,
                "inicio",
                "pilot",
                5,
                $now,
            ]);
            return [$userId, $orgId];
        });
    }
    public function register(Request $r): Response
    {
        ApiError::require(
            $this->config["registration"],
            403,
            "REGISTRATION_CLOSED",
            "El registro público está cerrado.",
        );
        [$userId, $orgId] = $this->createAccount($r->body);
        Support::audit(
            $this->db,
            $r,
            "account.created",
            "organization",
            $orgId,
            $orgId,
        );
        $response = $this->session($userId, $orgId);
        $response->status = 201;
        return $response;
    }
    public function login(Request $r): Response
    {
        Validator::keys($r->body, ["email", "password"]);
        $email = Validator::email($r->body);
        $password = $r->body["password"] ?? null;
        ApiError::require(
            is_string($password) &&
                strlen($password) >= 1 &&
                strlen($password) <= 72,
            422,
            "VALIDATION_ERROR",
            "Contraseña inválida.",
        );
        $user = $this->db->one("SELECT * FROM users WHERE email=?", [$email]);
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid = password_verify($password, $user["password_hash"] ?? $dummy);
        ApiError::require(
            $user !== null && $valid,
            401,
            "INVALID_CREDENTIALS",
            "Credenciales inválidas.",
        );
        $membership = Support::record(
            $this->db->one(
                'SELECT organization_id FROM memberships WHERE user_id=? AND role!=\'disabled\' ORDER BY created_at,organization_id LIMIT 1',
                [$user["id"]],
            ),
        );
        if (password_needs_rehash($user["password_hash"], PASSWORD_DEFAULT)) {
            $this->db->run("UPDATE users SET password_hash=? WHERE id=?", [
                password_hash($password, PASSWORD_DEFAULT),
                $user["id"],
            ]);
        }
        if ($r->auth) {
            $this->db->run("DELETE FROM sessions WHERE token_hash=?", [
                $r->auth["sessionHash"],
            ]);
        }
        return $this->session($user["id"], $membership["organization_id"]);
    }
    public function me(Request $r): Response
    {
        $a = $this->require($r);
        return new Response([
            "user" => $this->user($a["userId"]),
            "organizationId" => $a["organizationId"],
            "role" => $a["role"],
            "csrfToken" => $a["csrf"],
            "memberships" => $this->db->all(
                "SELECT m.organization_id,m.role,o.name,o.kind FROM memberships m JOIN organizations o ON o.id=m.organization_id WHERE m.user_id=?",
                [$a["userId"]],
            ),
        ]);
    }
    public function logout(Request $r): Response
    {
        $a = $this->require($r);
        $this->db->run("DELETE FROM sessions WHERE token_hash=?", [
            $a["sessionHash"],
        ]);
        return new Response(
            null,
            204,
            [],
            [
                "name" => self::COOKIE,
                "value" => "",
                "options" => [
                    "expires" => 1,
                    "path" => "/",
                    "secure" => $this->config["secure"],
                    "httponly" => true,
                    "samesite" => "Lax",
                ],
            ],
        );
    }
    public function changePassword(Request $r): Response
    {
        $a = $this->require($r);
        Validator::keys($r->body, ["currentPassword", "password"]);
        $current = $r->body["currentPassword"] ?? null;
        ApiError::require(
            is_string($current) &&
                strlen($current) >= 1 &&
                strlen($current) <= 72,
            422,
            "VALIDATION_ERROR",
            "Contraseña inválida.",
        );
        $new = Validator::password($r->body);
        $user = $this->db->one("SELECT password_hash FROM users WHERE id=?", [
            $a["userId"],
        ]);
        ApiError::require(
            password_verify($current, $user["password_hash"]),
            401,
            "INVALID_CREDENTIALS",
            "Credenciales inválidas.",
        );
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $this->db->transaction(function () use ($a, $hash, $r) {
            $this->db->run("UPDATE users SET password_hash=? WHERE id=?", [
                $hash,
                $a["userId"],
            ]);
            $this->db->run("DELETE FROM sessions WHERE user_id=?", [
                $a["userId"],
            ]);
            Support::audit(
                $this->db,
                $r,
                "password.changed",
                "user",
                $a["userId"],
            );
        });
        return $this->session($a["userId"], $a["organizationId"]);
    }
    public function switchOrganization(Request $r): Response
    {
        $a = $this->require($r);
        Validator::keys($r->body, ["organizationId"]);
        $org = Validator::id($r->body, "organizationId");
        Support::record(
            $this->db->one(
                'SELECT role FROM memberships WHERE organization_id=? AND user_id=? AND role!=\'disabled\'',
                [$org, $a["userId"]],
            ),
        );
        $this->db->run("DELETE FROM sessions WHERE token_hash=?", [
            $a["sessionHash"],
        ]);
        return $this->session($a["userId"], $org);
    }
}
