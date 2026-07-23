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

    public static function totalCount(): int
    {
        return (int) \getPDO()->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    }

    /** Fallzahl je Status, inkl. Status ohne laufende Fälle (Wert 0). */
    public static function countsByStatus(): array
    {
        $counts = array_fill_keys(['OPEN', 'PENDING', 'RESOLVED', 'CLOSED'], 0);
        $rows = \getPDO()->query('SELECT status, COUNT(*) AS c FROM tickets GROUP BY status')->fetchAll();
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    /** Fallzahl je Priorität, inkl. Prioritäten ohne laufende Fälle (Wert 0). */
    public static function countsByPriority(): array
    {
        $counts = array_fill_keys(['LOW', 'MEDIUM', 'HIGH', 'URGENT'], 0);
        $rows = \getPDO()->query('SELECT priority, COUNT(*) AS c FROM tickets GROUP BY priority')->fetchAll();
        foreach ($rows as $row) {
            $counts[$row['priority']] = (int) $row['c'];
        }
        return $counts;
    }

    /** Neu eingegangene Fälle pro Tag der letzten $days Tage (inkl. Tage ohne Eingang, Wert 0). */
    public static function createdPerDay(int $days): array
    {
        $stmt = \getPDO()->prepare(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM tickets
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)'
        );
        $stmt->execute([$days - 1]);
        $byDate = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDate[$row['d']] = (int) $row['c'];
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $series[] = ['date' => $date, 'count' => $byDate[$date] ?? 0];
        }
        return $series;
    }

    /** Fallzahl je Team, absteigend sortiert. Fälle ohne Team laufen unter "Kein Team". */
    public static function countsByTeam(): array
    {
        $rows = \getPDO()->query(
            'SELECT COALESCE(tm.name, "Kein Team") AS team_name, COUNT(*) AS c
             FROM tickets t
             LEFT JOIN teams tm ON tm.id = t.team_id
             GROUP BY team_name
             ORDER BY c DESC'
        )->fetchAll();
        return array_map(fn ($r) => ['label' => $r['team_name'], 'count' => (int) $r['c']], $rows);
    }

    /** Offene/wartende Fälle je zugewiesenem Agent, absteigend sortiert. */
    public static function openCountsByAgent(): array
    {
        $rows = \getPDO()->query(
            'SELECT COALESCE(u.name, "Nicht zugewiesen") AS agent_name, COUNT(*) AS c
             FROM tickets t
             LEFT JOIN users u ON u.id = t.assigned_to_id
             WHERE t.status IN ("OPEN", "PENDING")
             GROUP BY agent_name
             ORDER BY c DESC'
        )->fetchAll();
        return array_map(fn ($r) => ['label' => $r['agent_name'], 'count' => (int) $r['c']], $rows);
    }

    /** Durchschnittliche Zeit bis zur ersten Antwort in Stunden (nur Fälle mit mind. einer Antwort). */
    public static function avgFirstResponseHours(): ?float
    {
        $row = \getPDO()->query(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, t.created_at, first_reply.first_at)) AS avg_seconds
             FROM tickets t
             INNER JOIN (
                 SELECT ticket_id, MIN(created_at) AS first_at
                 FROM messages
                 WHERE direction = "OUTGOING"
                 GROUP BY ticket_id
             ) first_reply ON first_reply.ticket_id = t.id'
        )->fetch();

        if (!$row || $row['avg_seconds'] === null) {
            return null;
        }
        return round(((float) $row['avg_seconds']) / 3600, 1);
    }
}
