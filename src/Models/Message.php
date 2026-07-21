<?php
declare(strict_types=1);

namespace App\Models;

class Message
{
    public static function forTicket(int $ticketId): array
    {
        $stmt = \getPDO()->prepare(
            'SELECT m.*, u.name AS author_name
             FROM messages m
             LEFT JOIN users u ON u.id = m.author_user_id
             WHERE m.ticket_id = ?
             ORDER BY m.created_at ASC'
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public static function lastIncoming(int $ticketId): ?array
    {
        $stmt = \getPDO()->prepare(
            'SELECT * FROM messages WHERE ticket_id = ? AND direction = "INCOMING"
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(
        int $ticketId,
        string $direction,
        ?string $body,
        ?string $authorEmail = null,
        ?int $authorUserId = null,
        ?string $messageId = null,
        ?string $inReplyTo = null
    ): int {
        $stmt = \getPDO()->prepare(
            'INSERT INTO messages (ticket_id, direction, author_email, author_user_id, body, message_id, in_reply_to)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$ticketId, $direction, $authorEmail, $authorUserId, $body, $messageId, $inReplyTo]);
        return (int) \getPDO()->lastInsertId();
    }

    /** Sucht eine Nachricht anhand einer Liste möglicher Message-IDs (für Threading). */
    public static function findByMessageIds(array $messageIds): ?array
    {
        $messageIds = array_values(array_filter($messageIds));
        if (empty($messageIds)) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = \getPDO()->prepare("SELECT * FROM messages WHERE message_id IN ($placeholders) LIMIT 1");
        $stmt->execute($messageIds);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
