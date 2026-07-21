<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\ImapFetcher;

// CLI-Aufruf (Plesk "Geplante Aufgabe" mit direktem Befehl, z.B. via PHP-CLI):
// php /pfad/zu/TicketSystem/cron/poll_mailbox.php --token=DEIN_CRON_SECRET
$isCli = (php_sapi_name() === 'cli');

$providedToken = null;
if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with($arg, '--token=')) {
            $providedToken = substr($arg, 8);
        }
    }
} else {
    $providedToken = $_GET['token'] ?? ($_SERVER['HTTP_X_CRON_SECRET'] ?? null);
}

$expected = config('cron_secret');

if (empty($expected) || $providedToken !== $expected) {
    if (!$isCli) {
        http_response_code(403);
    }
    echo json_encode(['ok' => false, 'error' => 'Ungültiges oder fehlendes Token']);
    exit(1);
}

try {
    $result = ImapFetcher::poll();
    if (!$isCli) {
        header('Content-Type: application/json');
    }
    echo json_encode($result);
} catch (\Throwable $e) {
    error_log('[cron/poll_mailbox] ' . $e->getMessage());
    if (!$isCli) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['ok' => false, 'error' => 'Fehler beim Mailbox-Abruf']);
    exit(1);
}
