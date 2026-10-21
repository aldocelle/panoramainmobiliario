<?php
declare(strict_types=1);
require dirname(__DIR__) . "/bootstrap.php";
$db = new Panorama\Database(Panorama\Config::read());
$db->transaction(function () use ($db) {
    $db->run("DELETE FROM password_resets WHERE expires_at<?", [time()]);
    $db->run(
        "DELETE FROM mail_outbox WHERE status='sent' AND payload_cipher='' AND available_at<?",
        [time() - 2592000],
    );
    $db->run("DELETE FROM sessions WHERE expires_at<?", [time()]);
    $db->run("DELETE FROM rate_limits WHERE expires_at<?", [time()]);
    $db->run(
        "DELETE FROM invitations WHERE expires_at<? AND accepted_at IS NULL",
        [time()],
    );
});
echo "Sesiones, invitaciones vencidas y contadores expirados limpiados.\n";
