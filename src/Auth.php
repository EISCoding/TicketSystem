<?php
declare(strict_types=1);

namespace App;

class Auth
{
    private const MAX_FAILED_LOGINS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Versucht einen Login. Gibt bei Erfolg true zurück, sonst false.
     * Enthält Brute-Force-Schutz per Sperre nach mehreren Fehlversuchen.
     */
    public static function attemptLogin(string $email, string $password): bool
    {
        $pdo = \getPDO();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Bewusst gleiche Laufzeit wie bei falschem Passwort (Timing-Angriffe erschweren)
            password_verify($password, '$2y$10$invalidinvalidinvalidinvalidinvalidinva');
            return false;
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            $failed = (int) $user['failed_logins'] + 1;
            $lockUntil = null;
            if ($failed >= self::MAX_FAILED_LOGINS) {
                $lockUntil = date('Y-m-d H:i:s', time() + self::LOCKOUT_MINUTES * 60);
                $failed = 0;
            }
            $upd = $pdo->prepare('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?');
            $upd->execute([$failed, $lockUntil, $user['id']]);
            return false;
        }

        // Erfolgreicher Login: Zähler zurücksetzen
        $pdo->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')
            ->execute([$user['id']]);

        // Modernen Hash nachziehen, falls Kostparameter sich geändert haben
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $user['id']]);
        }

        // Session-Fixation verhindern
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function userName(): string
    {
        return $_SESSION['user_name'] ?? '';
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'ADMIN';
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Nur für Admins zugänglich.');
        }
    }

    /** Erzeugt (oder liefert bestehendes) CSRF-Token für die aktuelle Session. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Als verstecktes Formularfeld einfügen: <?= Auth::csrfField() ?> */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . \e(self::csrfToken()) . '">';
    }

    /** Bricht die Anfrage ab, falls das CSRF-Token fehlt oder ungültig ist. */
    public static function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            die('Ungültige Anfrage (CSRF-Token fehlt oder abgelaufen). Bitte Seite neu laden.');
        }
    }
}
