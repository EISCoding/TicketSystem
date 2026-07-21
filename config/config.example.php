<?php
/**
 * Konfigurationsdatei.
 *
 * WICHTIG: Diese Datei als "config.php" (ohne .example) speichern und mit echten
 * Zugangsdaten befüllen. Der Ordner "config/" liegt zwar im selben Verzeichnis wie die
 * öffentlichen Seiten, ist aber über die dortige .htaccess-Datei komplett gegen direkten
 * Browser-Zugriff gesperrt (Require all denied) — bitte diese .htaccess nicht löschen.
 */

return [
    // MySQL-Zugangsdaten (Plesk: Websites & Domains -> Datenbanken)
    'db' => [
        'host' => 'localhost',
        'name' => 'plesk_db_name',
        'user' => 'plesk_db_user',
        'pass' => 'plesk_db_passwort',
    ],

    // Langer, zufälliger String (z.B. mit bin2hex(random_bytes(32)) erzeugen).
    // Wird u.a. für zusätzliche Session-Härtung verwendet.
    'app_secret' => 'bitte-durch-einen-langen-zufaelligen-string-ersetzen',

    // Token zum einmaligen Ausführen von install.php (danach install.php löschen!)
    'setup_token' => 'bitte-durch-einen-langen-zufaelligen-string-ersetzen',

    // Token für den cron/poll_mailbox.php Endpoint (Plesk "URL aufrufen"-Scheduled-Task)
    'cron_secret' => 'bitte-durch-einen-langen-zufaelligen-string-ersetzen',

    // Initialer Admin-Zugang (nur bei install.php relevant)
    'seed_admin_email' => 'admin@example.com',
    'seed_admin_password' => 'bitte-aendern-123',

    // IMAP (Posteingang abrufen)
    'imap' => [
        'host' => 'imap.deinefirma.de',
        'port' => 993,
        'encryption' => 'ssl', // 'ssl', 'tls' oder null
        'validate_cert' => true,
        'username' => 'support@deinefirma.de',
        'password' => 'imap-passwort',
    ],

    // SMTP (Antworten versenden)
    'smtp' => [
        'host' => 'smtp.deinefirma.de',
        'port' => 587,
        'encryption' => 'tls', // 'tls', 'ssl' oder null
        'username' => 'support@deinefirma.de',
        'password' => 'smtp-passwort',
        'from_email' => 'support@deinefirma.de',
        'from_name' => 'Support-Team',
    ],

    'mail_domain' => 'deinefirma.de',
];
