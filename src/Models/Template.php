<?php
declare(strict_types=1);

namespace App\Models;

class Template
{
    public static function all(): array
    {
        return \getPDO()->query('SELECT * FROM email_templates ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = \getPDO()->prepare('SELECT * FROM email_templates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $subject, string $body): void
    {
        $stmt = \getPDO()->prepare('INSERT INTO email_templates (name, subject, body) VALUES (?, ?, ?)');
        $stmt->execute([$name, $subject, $body]);
    }

    public static function delete(int $id): void
    {
        \getPDO()->prepare('DELETE FROM email_templates WHERE id = ?')->execute([$id]);
    }

    /** Ersetzt Platzhalter wie {{ticket.number}}, {{requester.name}}, {{agent.name}}. */
    public static function render(string $source, array $context): string
    {
        $flat = [];
        foreach ($context as $group => $values) {
            foreach ($values as $key => $value) {
                $flat['{{' . $group . '.' . $key . '}}'] = $value;
            }
        }
        return strtr($source, $flat);
    }
}
