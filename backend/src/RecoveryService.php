<?php
declare(strict_types=1);
namespace Panorama;
final class RecoveryService
{
    public function __construct(private Database $db, private array $config) {}
    public static function encrypt(array $payload, string $key): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode(
            $nonce .
                sodium_crypto_secretbox(
                    Support::json($payload),
                    $nonce,
                    hash("sha256", $key, true),
                ),
        );
    }
    public static function decrypt(string $payload, string $key): array
    {
        $bytes = base64_decode($payload, true);
        if (
            $bytes === false ||
            strlen($bytes) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        ) {
            throw new \RuntimeException("Cola inválida.");
        }
        $plain = sodium_crypto_secretbox_open(
            substr($bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            hash("sha256", $key, true),
        );
        if ($plain === false) {
            throw new \RuntimeException("No se pudo descifrar la cola.");
        }
        return json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
    }
    public function request(Request $r): Response
    {
        Validator::keys($r->body, ["email"]);
        $email = Validator::email($r->body);
        ApiError::require(
            $this->config["mailTransport"] !== "disabled" &&
                $this->config["webBase"] !== "",
            503,
            "RECOVERY_NOT_CONFIGURED",
            "La recuperación por correo aún no está habilitada.",
        );
        $user = $this->db->one("SELECT id FROM users WHERE email=?", [$email]);
        if ($user) {
            $token = Support::token();
            $hash = Support::hash($token);
            $now = Support::now();
            $payload = [
                "to" => $email,
                "subject" => "Recupera tu acceso a Panorama Inmobiliario",
                "body" =>
                    "Solicitaste restablecer tu contraseña. El enlace vence en 30 minutos y solo puede utilizarse una vez: " .
                    $this->config["webBase"] .
                    "/#reset-password?token=" .
                    rawurlencode($token),
            ];
            $this->db->transaction(function () use (
                $user,
                $hash,
                $now,
                $payload,
            ) {
                $this->db->run(
                    "DELETE FROM password_resets WHERE user_id=? AND used_at IS NULL",
                    [$user["id"]],
                );
                $this->db->run(
                    "INSERT INTO password_resets VALUES(?,?,?,?,?)",
                    [$hash, $user["id"], time() + 1800, null, $now],
                );
                $this->db->run(
                    "INSERT INTO mail_outbox VALUES(?,?,?,?,?,?,?,?,?)",
                    [
                        Support::uuid(),
                        self::encrypt($payload, $this->config["key"]),
                        "pending",
                        0,
                        time(),
                        null,
                        null,
                        $now,
                        null,
                    ],
                );
            });
        }
        // Same response for existing/nonexistent addresses, never expose the token.
        return new Response(
            [
                "accepted" => true,
                "message" =>
                    "Si la cuenta existe, se ha solicitado el proceso de recuperación.",
            ],
            202,
        );
    }
    public function reset(Request $r): Response
    {
        Validator::keys($r->body, ["token", "password"]);
        $token = Validator::string($r->body, "token", 40, 100);
        $new = Validator::password($r->body);
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $this->db->transaction(function () use ($r, $token, $hash) {
            $reset = Support::record(
                $this->db->one(
                    "SELECT * FROM password_resets WHERE token_hash=?" .
                        ($this->db->driver === "mysql" ? " FOR UPDATE" : ""),
                    [Support::hash($token)],
                ),
            );
            ApiError::require(
                $reset["used_at"] === null &&
                    (int) $reset["expires_at"] > time(),
                400,
                "RESET_INVALID",
                "El enlace no es válido o ha vencido.",
            );
            $this->db->run("UPDATE users SET password_hash=? WHERE id=?", [
                $hash,
                $reset["user_id"],
            ]);
            $this->db->run(
                "UPDATE password_resets SET used_at=? WHERE user_id=?",
                [Support::now(), $reset["user_id"]],
            );
            $this->db->run("DELETE FROM sessions WHERE user_id=?", [
                $reset["user_id"],
            ]);
            $members = $this->db->all(
                "SELECT organization_id FROM memberships WHERE user_id=? AND role!='disabled'",
                [$reset["user_id"]],
            );
            foreach ($members as $member) {
                Support::audit(
                    $this->db,
                    $r,
                    "password.reset",
                    "user",
                    $reset["user_id"],
                    $member["organization_id"],
                );
            }
        });
        return new Response(["reset" => true, "sessionsRevoked" => true]);
    }
}
