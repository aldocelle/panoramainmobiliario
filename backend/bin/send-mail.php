<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
use Panorama\{Config, Database, RecoveryService, Support};
// Run every minute from cron. Delivery is at least once; a crash after mail()
// may result in a duplicate message. Never print addresses or recovery links.
try {
    $config = Config::read();
    if ($config["mailTransport"] !== "native") {
        throw new RuntimeException(
            "Habilita el transporte native antes de ejecutar el correo.",
        );
    }
    $from = $config["mailFrom"];
    if (
        !filter_var($from, FILTER_VALIDATE_EMAIL) ||
        str_contains($from, "\r") ||
        str_contains($from, "\n")
    ) {
        throw new RuntimeException("MAIL_FROM debe ser una dirección válida.");
    }
    $db = new Database($config);
    $processed = 0;
    $db->run(
        "UPDATE mail_outbox SET status='pending',locked_at=NULL WHERE status='processing' AND locked_at<?",
        [time() - 600],
    );
    for ($i = 0; $i < 50; $i++) {
        $row = $db->transaction(function () use ($db) {
            $row = $db->one(
                "SELECT * FROM mail_outbox WHERE status='pending' AND available_at<=? ORDER BY created_at,id LIMIT 1" .
                    ($db->driver === "mysql" ? " FOR UPDATE" : ""),
                [time()],
            );
            if (!$row) {
                return null;
            }
            $db->run(
                "UPDATE mail_outbox SET status='processing',locked_at=?,attempts=attempts+1 WHERE id=?",
                [time(), $row["id"]],
            );
            return $row;
        });
        if (!$row) {
            break;
        }
        try {
            $message = RecoveryService::decrypt(
                $row["payload_cipher"],
                $config["key"],
            );
            if (
                !filter_var($message["to"] ?? "", FILTER_VALIDATE_EMAIL) ||
                preg_match('/[\r\n]/', $message["to"])
            ) {
                throw new RuntimeException("Invalid recipient");
            }
            $ok = mail(
                $message["to"],
                "=?UTF-8?B?" . base64_encode($message["subject"]) . "?=",
                $message["body"],
                [
                    "From" => $from,
                    "MIME-Version" => "1.0",
                    "Content-Type" => "text/plain; charset=UTF-8",
                ],
            );
            if (!$ok) {
                throw new RuntimeException("Transport rejected message");
            }
            // Remove token and recipient from delivered payloads.
            $db->run(
                "UPDATE mail_outbox SET status='sent',sent_at=?,payload_cipher='',locked_at=NULL,last_error=NULL WHERE id=?",
                [Support::now(), $row["id"]],
            );
            $processed++;
        } catch (Throwable) {
            $attempt = (int) $row["attempts"] + 1;
            $status = $attempt >= 5 ? "failed" : "pending";
            $db->run(
                "UPDATE mail_outbox SET status=?,available_at=?,locked_at=NULL,last_error=? WHERE id=?",
                [
                    $status,
                    time() + min(3600, 60 * 2 ** $attempt),
                    "DELIVERY_FAILED",
                    $row["id"],
                ],
            );
        }
    }
    echo "Mensajes aceptados por transporte: $processed\n";
} catch (Throwable) {
    fwrite(
        STDERR,
        "No se pudo procesar la cola. Comprueba configuración y transporte.\n",
    );
    exit(1);
}
