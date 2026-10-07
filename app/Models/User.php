<?php

declare(strict_types=1);

namespace App\Models;

final class User extends Model
{
    public function findByEmail(string $email): ?array
    {
        $query = $this->db()->prepare('SELECT id, name, email, phone, password_hash, role, status, created_at FROM users WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        return $query->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $query = $this->db()->prepare('SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ? LIMIT 1');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function createCustomer(string $name, string $email, string $password, ?string $phone): int
    {
        // The role is fixed server-side; registration can never create an admin.
        $query = $this->db()->prepare("INSERT INTO users (name, email, password_hash, phone, role, status) VALUES (?, ?, ?, ?, 'customer', 'active')");
        $query->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
        return (int) $this->db()->lastInsertId();
    }
}
