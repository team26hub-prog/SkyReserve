<?php

declare(strict_types=1);

namespace App\Models;

final class Airport extends Model
{
    public function all(): array
    {
        return $this->db()->query('SELECT * FROM airports ORDER BY iata_code')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $query = $this->db()->prepare('SELECT * FROM airports WHERE id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function save(array $values, ?int $id = null): void
    {
        if ($id === null) {
            // Preserve the existing required timezone field without adding future UI.
            $sql = "INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, ?, 'UTC')";
        } else {
            $sql = 'UPDATE airports SET iata_code = ?, name = ?, city = ?, country = ? WHERE id = ?';
        }
        $params = [$values['iata_code'], $values['name'], $values['city'], $values['country']];
        if ($id !== null) {
            $params[] = $id;
        }
        $this->db()->prepare($sql)->execute($params);
    }

    public function delete(int $id): void
    {
        $this->db()->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]);
    }
}
