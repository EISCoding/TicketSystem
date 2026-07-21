<?php
require_once __DIR__ . '/src/bootstrap.php';

/**
 * WICHTIG: Diese Datei nach erfolgreicher Installation LÖSCHEN oder zumindest
 * per .htaccess sperren - sie darf nicht dauerhaft öffentlich erreichbar bleiben.
 */

$providedToken = $_GET['token'] ?? '';
$expected = config('setup_token');

if (empty($expected) || $expected === 'bitte-durch-einen-langen-zufaelligen-string-ersetzen' || !hash_equals($expected, $providedToken)) {
    http_response_code(403);
    die('Ungültiges Setup-Token. Bitte "setup_token" in config/config.php prüfen und als ?token=... anhängen.');
}

$pdo = getPDO();
$log = [];

// 1. Tabellen anlegen
$schemaPath = __DIR__ . '/sql/schema.sql';
$sql = file_get_contents($schemaPath);
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $stmt) {
    if ($stmt === '') {
        continue;
    }
    $pdo->exec($stmt);
}
$log[] = 'Datenbank-Tabellen erstellt bzw. bereits vorhanden.';

// 2. Admin-User anlegen (falls noch keiner existiert)
$adminEmail = config('seed_admin_email', 'admin@example.com');
$adminPassword = config('seed_admin_password', 'admin123');
$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$adminEmail]);
if (!$check->fetch()) {
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "ADMIN")')
        ->execute(['Admin', $adminEmail, $hash]);
    $log[] = "Admin-User erstellt: $adminEmail";
} else {
    $log[] = 'Admin-User existiert bereits, übersprungen.';
}

// 3. Standard-Teams anlegen
$teamCount = (int) $pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
if ($teamCount === 0) {
    $pdo->exec("INSERT INTO teams (name, keywords, is_default) VALUES ('Allgemein', '', 1)");
    $pdo->exec("INSERT INTO teams (name, keywords, is_default) VALUES ('Rechnungen', 'rechnung,zahlung,billing,invoice', 0)");
    $pdo->exec("INSERT INTO teams (name, keywords, is_default) VALUES ('Technik', 'bug,fehler,technisch,defekt,kaputt', 0)");
    $log[] = 'Standard-Teams erstellt.';
} else {
    $log[] = 'Teams existieren bereits, übersprungen.';
}

// 4. Beispiel-Vorlagen anlegen
$tplCount = (int) $pdo->query('SELECT COUNT(*) FROM email_templates')->fetchColumn();
if ($tplCount === 0) {
    $stmt = $pdo->prepare('INSERT INTO email_templates (name, subject, body) VALUES (?, ?, ?)');
    $stmt->execute(['Erste Antwort', 'Wir haben Ihre Anfrage erhalten',
        "Hallo {{requester.name}},\n\nvielen Dank für Ihre Nachricht (Ticket #{{ticket.number}}). Wir kümmern uns schnellstmöglich darum.\n\nViele Grüße\n{{agent.name}}"]);
    $stmt->execute(['Ticket gelöst', 'Ihr Ticket wurde gelöst',
        "Hallo {{requester.name}},\n\nwir haben Ihr Anliegen (Ticket #{{ticket.number}}) bearbeitet und als gelöst markiert. Bei weiteren Fragen antworten Sie einfach auf diese Email.\n\nViele Grüße\n{{agent.name}}"]);
    $stmt->execute(['Rückfrage', 'Kurze Rückfrage zu Ihrem Ticket',
        "Hallo {{requester.name}},\n\nkönnten Sie uns bitte weitere Informationen zu Ihrem Anliegen (Ticket #{{ticket.number}}) zukommen lassen?\n\nViele Grüße\n{{agent.name}}"]);
    $log[] = 'Beispiel-Vorlagen erstellt.';
} else {
    $log[] = 'Vorlagen existieren bereits, übersprungen.';
}

header('Content-Type: text/plain; charset=utf-8');
echo "Installation abgeschlossen.\n\n";
foreach ($log as $line) {
    echo "- $line\n";
}
echo "\nWICHTIG: Bitte diese Datei (install.php) jetzt löschen oder den Zugriff sperren!\n";
echo "Login: $adminEmail (Passwort wie in config.php hinterlegt)\n";
