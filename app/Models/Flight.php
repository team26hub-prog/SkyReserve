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

    public function searchAvailable(int $originId, int $destinationId, string $date): array
    {
        $query = $this->db()->prepare($this->customerQuery('f.origin_airport_id = ? AND f.destination_airport_id = ? AND f.departure_at >= ? AND f.departure_at <= ?') . ' ORDER BY departure_at, flight_number, id');
        $query->execute([gmdate('Y-m-d H:i:s'), $originId, $destinationId, $date . ' 00:00:00', $date . ' 23:59:59']);
        return $query->fetchAll();
    }

    public function findAvailable(int $id): ?array
    {
        $query = $this->db()->prepare($this->customerQuery('f.id = ?'));
        $query->execute([gmdate('Y-m-d H:i:s'), $id]);
        return $query->fetch() ?: null;
    }

    public function upcomingAvailable(): array
    {
        $query = $this->db()->prepare($this->customerQuery('1 = 1') . ' ORDER BY departure_at, flight_number, id LIMIT 6');
        $query->execute([gmdate('Y-m-d H:i:s')]);
        return $query->fetchAll();
    }

    private function customerQuery(string $condition): string
    {
        // Conditions are internal SQL only. Availability counts configured, active
        // seats without a reserved/confirmed allocation for this specific flight.
        return 'SELECT eligible.*, (SELECT COUNT(*) FROM seats s
            WHERE s.aircraft_id = eligible.aircraft_id AND s.status = \'active\'
            AND NOT EXISTS (SELECT 1 FROM booking_seats bs WHERE bs.flight_id = eligible.id
                AND bs.seat_id = s.id AND bs.status IN (\'reserved\', \'confirmed\'))) AS available_seats
            FROM (' . self::SELECT . ' WHERE f.status = \'scheduled\' AND f.departure_at > ?
                AND origin.status = \'active\' AND destination.status = \'active\' AND a.status = \'active\'
                AND ' . $condition . ') eligible HAVING available_seats > 0';
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
