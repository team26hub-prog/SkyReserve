<?php

declare(strict_types=1);

namespace App\Models;

use OutOfBoundsException;
use Throwable;

final class Flight extends Model
{
    public const STATUSES = ['scheduled' => 'Scheduled', 'delayed' => 'Delayed', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];

    private const SELECT = 'SELECT f.*, origin.iata_code AS origin_code, origin.name AS origin_name, origin.city AS origin_city,
        destination.iata_code AS destination_code, destination.name AS destination_name, destination.city AS destination_city,
        a.model AS aircraft_model, a.registration_number, a.total_capacity
        FROM flights f JOIN airports origin ON origin.id = f.origin_airport_id
        JOIN airports destination ON destination.id = f.destination_airport_id
        JOIN aircraft a ON a.id = f.aircraft_id';

    public function all(): array
    {
        return $this->db()->query(self::SELECT . ' ORDER BY f.departure_at DESC, f.flight_number, f.id')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $query = $this->db()->prepare(self::SELECT . ' WHERE f.id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public function save(array $values, ?int $id = null): void
    {
        $params = [$values['flight_number'], $values['origin_airport_id'], $values['destination_airport_id'], $values['aircraft_id'],
            $values['departure_at'], $values['arrival_at'], $values['base_fare'], $values['status']];
        if ($id === null) {
            $this->db()->prepare('INSERT INTO flights (flight_number, origin_airport_id, destination_airport_id, aircraft_id, departure_at, arrival_at, base_fare, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($params);
            return;
        }
        $db = $this->db();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            if ($query->fetchColumn() === false) {
                throw new OutOfBoundsException('Flight not found.');
            }
            // Existing currency and timestamps remain managed by the database.
            $db->prepare('UPDATE flights SET flight_number = ?, origin_airport_id = ?, destination_airport_id = ?, aircraft_id = ?, departure_at = ?, arrival_at = ?, base_fare = ?, status = ? WHERE id = ?')->execute([...$params, $id]);
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $query = $this->db()->prepare('DELETE FROM flights WHERE id = ?');
        $query->execute([$id]);
        if ($query->rowCount() === 0) {
            throw new OutOfBoundsException('Flight not found.');
        }
    }
}
