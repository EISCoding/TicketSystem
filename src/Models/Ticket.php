<?php
declare(strict_types=1);

namespace App\Models;

class Ticket
{
    /** Liste mit optionalen Filtern. Nutzt ausschliesslich Prepared Statements. */
    public static function all(array $filters = []): array
    {
        $pdo = \getPDO();
        $sql = 'SELECT t.*, tm.name AS team_name, u.name AS assigned_name,
                       (SELECT COUNT(*) FROM messages m WHERE m.ticket_id = t.id) AS message_count
                FROM tickets t
                LEFT JOIN teams tm ON tm.id = t.team_id
                LEFT JOIN users u ON u.id = t.assigned_to_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['team_id'])) {
            $sql .= ' AND t.team_id = ?';
            $params[] = (int) $filters['team_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND t.subject LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY t.updated_at DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = \getPDO();
        $stmt = $pdo->prepare(
            'SELECT t.*, tm.name AS team_name, u.name AS assigned_name
             FROM tickets t
             LEFT JOIN teams tm ON tm.id = t.team_id
             LEFT JOIN users u ON u.id = t.assigned_to_id
             WHERE t.id = ?'
        );
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        return $ticket ?: null;
    }

    public static function create(string $subject, string $requesterEmail, ?string $requesterName, ?int $teamId): int
    {
        $pdo = \getPDO();
        $stmt = $pdo->prepare(
            'INSERT INTO tickets (subject, requester_email, requester_name, team_id, status, priority)
             VALUES (?, ?, ?, ?, "OPEN", "MEDIUM")'
        );
        $stmt->execute([$subject, $requesterEmail, $requesterName, $teamId]);
        return (int) $pdo->lastInsertId();
    }

    public static function updateFields(int $id, array $fields): void
    {
        $allowed = ['status', 'priority', 'team_id', 'assigned_to_id', 'subject'];
        $sets = [];
        $params = [];
        foreach ($fields as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $sets[] = "$key = ?";
            $params[] = $value === '' ? null : $value;
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $id;
        $pdo = \getPDO();
        $pdo->prepare('UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public static function touch(int $id): void
    {
        \getPDO()->prepare('UPDATE tickets SET updated_at = NOW() WHERE id = ?')->execute([$id]);
    }
}
