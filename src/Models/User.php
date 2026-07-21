<?php
declare(strict_types=1);

namespace App\Models;

class User
{
    public static function all(): array
    {
        return \getPDO()->query('SELECT id, name, email, role, created_at FROM users ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = \getPDO()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $email, string $password, string $role): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = \getPDO()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, $role === 'ADMIN' ? 'ADMIN' : 'AGENT']);
    }

    public static function delete(int $id): void
    {
        \getPDO()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }
}
