<?php
declare(strict_types=1);

// Zeigt Fehler nicht im Browser an (Produktions-Sicherheit) - Logging stattdessen aktivieren.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$configPath = __DIR__ . '/../config/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die('Konfigurationsdatei fehlt. Bitte config/config.example.php nach config/config.php kopieren und ausfüllen.');
}
$config = require $configPath;

// --- Sichere Session-Konfiguration ---
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_name('tsid');
session_start();

// --- PDO-Verbindung (mit echten Prepared Statements) ---
function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        global $config;
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['db']['host'],
            $config['db']['name']
        );
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function config(string $key, $default = null)
{
    global $config;
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escaped Ausgabe für HTML-Templates - IMMER für Nutzereingaben verwenden. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Formatiert eine Fallnummer fünfstellig mit führenden Nullen (1 -> "00001"). */
function caseNumber(int $id): string
{
    return str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}
