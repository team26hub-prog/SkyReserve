<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use OutOfBoundsException;

final class Seat extends Model
{
    public function allForAircraft(int $aircraftId): array
    {
        $query = $this->db()->prepare('SELECT * FROM seats WHERE aircraft_id = ? ORDER BY LENGTH(seat_number), seat_number');
        $query->execute([$aircraftId]);
        return $query->fetchAll();
    }

    public function find(int $id, int $aircraftId): ?array
    {
        $query = $this->db()->prepare('SELECT * FROM seats WHERE id = ? AND aircraft_id = ?');
        $query->execute([$id, $aircraftId]);
        return $query->fetch() ?: null;
    }

    public function save(int $aircraftId, array $values, ?int $id = null): void
    {
        (new Aircraft())->withLock($aircraftId, function (array $aircraft) use ($aircraftId, $values, $id): void {
            if ($id === null) {
                if ($aircraft['seat_count'] >= (int) $aircraft['total_capacity']) {
                    throw new DomainException('This aircraft is at capacity. Increase its capacity before adding another seat.');
                }
                $sql = 'INSERT INTO seats (seat_number, cabin_class, aircraft_id) VALUES (?, ?, ?)';
                $params = [$values['seat_number'], $values['cabin_class'], $aircraftId];
            } else {
                if (!$this->find($id, $aircraftId)) {
                    throw new OutOfBoundsException('Seat not found for this aircraft.');
                }
                $sql = 'UPDATE seats SET seat_number = ?, cabin_class = ? WHERE id = ? AND aircraft_id = ?';
                $params = [$values['seat_number'], $values['cabin_class'], $id, $aircraftId];
            }
            $this->db()->prepare($sql)->execute($params);
        });
    }

    public function delete(int $id, int $aircraftId): void
    {
        (new Aircraft())->withLock($aircraftId, function () use ($id, $aircraftId): void {
            if (!$this->find($id, $aircraftId)) {
                throw new OutOfBoundsException('Seat not found for this aircraft.');
            }
            $this->db()->prepare('DELETE FROM seats WHERE id = ? AND aircraft_id = ?')->execute([$id, $aircraftId]);
        });
    }
}
