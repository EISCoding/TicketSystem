<?php
declare(strict_types=1);

namespace App\Models;

class Team
{
    public static function all(): array
    {
        return \getPDO()->query('SELECT * FROM teams ORDER BY name ASC')->fetchAll();
    }

    public static function create(string $name, string $keywords, bool $isDefault): void
    {
        $pdo = \getPDO();
        if ($isDefault) {
            $pdo->exec('UPDATE teams SET is_default = 0');
        }
        $stmt = $pdo->prepare('INSERT INTO teams (name, keywords, is_default) VALUES (?, ?, ?)');
        $stmt->execute([$name, $keywords, $isDefault ? 1 : 0]);
    }

    public static function delete(int $id): void
    {
        \getPDO()->prepare('DELETE FROM teams WHERE id = ?')->execute([$id]);
    }

    /**
     * Ermittelt anhand von Keywords in Betreff + Text das passende Team.
     * Fällt auf das als Standard markierte Team zurück, falls kein Keyword passt.
     */
    public static function resolveForContent(string $subject, string $body): ?int
    {
        $teams = self::all();
        $haystack = mb_strtolower($subject . "\n" . $body);

        foreach ($teams as $team) {
            $keywords = array_filter(array_map('trim', explode(',', mb_strtolower($team['keywords']))));
            foreach ($keywords as $kw) {
                if ($kw !== '' && mb_strpos($haystack, $kw) !== false) {
                    return (int) $team['id'];
                }
            }
        }

        foreach ($teams as $team) {
            if ((int) $team['is_default'] === 1) {
                return (int) $team['id'];
            }
        }

        return null;
    }
}
