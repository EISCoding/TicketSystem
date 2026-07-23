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

/**
 * Bereinigt HTML aus dem WYSIWYG-Editor (Antworten, Vorlagen) auf eine kleine,
 * für Emails und die eigene Vorschau sichere Tag-/Attribut-Auswahl. Nur intern
 * (Admins/Agenten) erreichbar, aber Emails werden an echte Kunden verschickt und
 * dieselben Inhalte später ungeprüft im Ticket-Verlauf wieder angezeigt - daher
 * trotzdem serverseitig bereinigen statt dem Editor blind zu vertrauen.
 */
function sanitizeHtml(string $html): string
{
    $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'blockquote', 'h1', 'h2', 'h3'];
    $stripEntirely = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'form', 'input', 'button'];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="utf-8"?><div>' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
    );
    libxml_clear_errors();

    $root = $doc->getElementsByTagName('div')->item(0);
    if ($root === null) {
        return '';
    }

    // Quill stellt Aufzählungslisten intern als <ol><li data-list="bullet"> plus einem rein
    // optischen <span class="ql-ui">-Aufzählungszeichen dar. Außerhalb des Editors (Email,
    // Fall-Verlauf) ist weder Quills CSS noch das data-list-Attribut bekannt - ohne Umwandlung
    // in echtes <ul> würde daraus eine nummerierte statt eine Aufzählungsliste.
    foreach (iterator_to_array($doc->getElementsByTagName('span')) as $uiMark) {
        if ($uiMark instanceof DOMElement && str_contains((string) $uiMark->getAttribute('class'), 'ql-ui')) {
            $uiMark->parentNode?->removeChild($uiMark);
        }
    }
    foreach (iterator_to_array($doc->getElementsByTagName('ol')) as $ol) {
        if (!($ol instanceof DOMElement)) {
            continue;
        }
        $firstLi = null;
        foreach ($ol->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'li') {
                $firstLi = $child;
                break;
            }
        }
        if ($firstLi !== null && $firstLi->getAttribute('data-list') === 'bullet') {
            $ul = $doc->createElement('ul');
            while ($ol->firstChild) {
                $ul->appendChild($ol->firstChild);
            }
            $ol->parentNode?->replaceChild($ul, $ol);
        }
    }

    $clean = function (DOMNode $node) use (&$clean, $allowedTags, $stripEntirely): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) {
                continue; // DOMText etc. bleibt unverändert stehen
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, $stripEntirely, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, $allowedTags, true)) {
                // Unbekanntes/verbotenes Tag entfernen, Textinhalt aber behalten.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                if ($tag === 'a' && $attr->name === 'href') {
                    $href = trim($attr->value);
                    if (!preg_match('/^(https?:|mailto:)/i', $href)) {
                        $child->removeAttribute('href');
                    }
                    continue;
                }
                $child->removeAttribute($attr->name);
            }
            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
                $child->setAttribute('target', '_blank');
            }

            $clean($child);
        }
    };
    $clean($root);

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $doc->saveHTML($child);
    }
    return trim($result);
}

/**
 * Wandelt gespeicherte Nachrichten-/Vorlagen-Inhalte aus der Zeit vor dem WYSIWYG-Editor
 * (reiner Text mit "\n"-Zeilenumbrüchen) in sicheres HTML um. Enthält der Inhalt bereits
 * Tags, wird angenommen, dass er schon beim Speichern über sanitizeHtml() lief, und
 * unverändert durchgereicht.
 */
function ensureHtml(string $body): string
{
    if (preg_match('/<[a-z][\s\S]*>/i', $body)) {
        return $body;
    }
    return nl2br(e($body));
}

/** Prüft, ob vom Editor übermitteltes HTML nach Entfernen der Tags noch sichtbaren Inhalt hat. */
function isHtmlEmpty(string $html): bool
{
    return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) === '';
}
