<?php

declare(strict_types=1);

namespace App\Models;

use OutOfBoundsException;
use DomainException;
use Throwable;

final class Aircraft extends Model
{
    public function all(): array
    {
        return $this->db()->query('SELECT a.*, (SELECT COUNT(*) FROM seats s WHERE s.aircraft_id = a.id) AS seat_count FROM aircraft a ORDER BY model, registration_number')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $query = $this->db()->prepare('SELECT a.*, (SELECT COUNT(*) FROM seats s WHERE s.aircraft_id = a.id) AS seat_count FROM aircraft a WHERE a.id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function save(array $values, ?int $id = null): void
    {
        $params = [$values['model'], $values['registration_number'], $values['total_capacity']];
        if ($id === null) {
            $this->db()->prepare('INSERT INTO aircraft (model, registration_number, total_capacity) VALUES (?, ?, ?)')->execute($params);
            return;
        }
        $this->withLock($id, function (array $aircraft) use ($values, $params, $id): void {
            if ((int) $values['total_capacity'] < $aircraft['seat_count']) {
                throw new DomainException('Capacity cannot be smaller than the number of existing seats.');
            }
            $this->db()->prepare('UPDATE aircraft SET model = ?, registration_number = ?, total_capacity = ? WHERE id = ?')->execute([...$params, $id]);
        });
    }

    public function delete(int $id): void
    {
        $this->withLock($id, function (array $aircraft) use ($id): void {
            if ($aircraft['seat_count'] > 0) {
                throw new DomainException('Delete this aircraft’s seats before deleting the aircraft.');
            }
            $this->db()->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$id]);
        });
    }

    // All seat mutations and capacity updates lock this parent first.
    public function withLock(int $id, callable $work): mixed
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM aircraft WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $aircraft = $query->fetch();
            if (!$aircraft) {
                throw new OutOfBoundsException('Aircraft not found.');
            }
            $query = $db->prepare('SELECT COUNT(*) FROM seats WHERE aircraft_id = ?');
            $query->execute([$id]);
            $aircraft['seat_count'] = (int) $query->fetchColumn();
            $result = $work($aircraft);
            $db->commit();
            return $result;
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
