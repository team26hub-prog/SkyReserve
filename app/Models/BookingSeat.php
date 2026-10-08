<?php
declare(strict_types=1);

namespace App\Models;

use DomainException;
use OutOfBoundsException;
use PDOException;
use Throwable;

final class BookingSeat extends Model
{
    public function context(int $bookingId, int $customerId): array
    {
        $booking = (new Booking())->findForCustomer($bookingId, $customerId);
        if (!$booking) {
            throw new OutOfBoundsException('Booking not found.');
        }
        $flight = (new Flight())->find((int) $booking['flight_id']);
        $this->validate($booking, $flight);
        return [
            'booking' => $booking,
            'flight' => $flight,
            'passengers' => (new Passenger())->forBooking($bookingId),
        ];
    }

    private function validate(array $booking, ?array $flight): void
    {
        if (!in_array($booking['status'], ['pending', 'payment_submitted', 'confirmed'], true)
            || ($booking['status'] !== 'confirmed' && $booking['expires_at'] !== null && $booking['expires_at'] <= gmdate('Y-m-d H:i:s'))
            || !$flight
            || !in_array($flight['status'], ['scheduled', 'delayed'], true)
            || $flight['departure_at'] <= gmdate('Y-m-d H:i:s')) {
            throw new DomainException('Seat selection is unavailable for this booking or flight.');
        }
        $query = $this->db()->prepare("SELECT COUNT(*) FROM aircraft a
            JOIN airports o ON o.id = ? JOIN airports d ON d.id = ?
            WHERE a.id = ? AND a.status = 'active' AND o.status = 'active' AND d.status = 'active'");
        $query->execute([$flight['origin_airport_id'], $flight['destination_airport_id'], $flight['aircraft_id']]);
        if (!(int) $query->fetchColumn()) {
            throw new DomainException('The flight’s aircraft or route is unavailable.');
        }
    }

    public function map(int $aircraftId, int $flightId): array
    {
        $query = $this->db()->prepare("SELECT s.*, bs.booking_id AS occupant_booking_id, bs.passenger_id AS occupant_passenger_id FROM seats s
            LEFT JOIN booking_seats bs ON bs.seat_id = s.id AND bs.flight_id = ? AND bs.status IN ('reserved','confirmed')
            WHERE s.aircraft_id = ? ORDER BY LENGTH(s.seat_number), s.seat_number");
        $query->execute([$flightId, $aircraftId]);
        return $query->fetchAll();
    }

    public function forBooking(int $id): array
    {
        $query = $this->db()->prepare('SELECT bs.*, s.seat_number, s.cabin_class
            FROM booking_seats bs JOIN seats s ON s.id = bs.seat_id WHERE bs.booking_id = ?');
        $query->execute([$id]);
        return $query->fetchAll();
    }

    public function assign(int $bookingId, int $customerId, int $passengerId, int $seatId): void
    {
        $db = $this->db();
        $initial = (new Booking())->findForCustomer($bookingId, $customerId);
        if (!$initial) {
            throw new OutOfBoundsException('Booking not found.');
        }
        // Keep this lookup outside the transaction so waiting cannot retain a stale snapshot.
        $db->beginTransaction();
        try {
            // Resolve ownership before locking, then recheck after acquiring locks.
            $query = $db->prepare('SELECT aircraft_id FROM flights WHERE id = ? FOR UPDATE');
            $query->execute([$initial['flight_id']]);
            $aircraftId = $query->fetchColumn();
            if ($aircraftId === false) {
                throw new DomainException('The flight is unavailable.');
            }
            $query = $db->prepare('SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE');
            $query->execute([$bookingId, $customerId]);
            $booking = $query->fetch();
            if (!$booking) {
                throw new OutOfBoundsException('Booking not found.');
            }
            if ((int) $booking['flight_id'] !== (int) $initial['flight_id']) {
                throw new DomainException('Booking flight changed. Reload the seat map.');
            }
            $db->prepare('SELECT id FROM aircraft WHERE id = ? FOR UPDATE')->execute([$aircraftId]);
            $flight = (new Flight())->find((int) $booking['flight_id']);
            $this->validate($booking, $flight);
            $query = $db->prepare("SELECT id FROM passengers WHERE id = ? AND booking_id = ? AND status = 'active' FOR UPDATE");
            $query->execute([$passengerId, $bookingId]);
            if ($query->fetchColumn() === false) {
                throw new DomainException('Select a passenger belonging to this booking.');
            }
            $query = $db->prepare('SELECT id FROM booking_seats WHERE booking_id = ? AND passenger_id = ? FOR UPDATE');
            $query->execute([$bookingId, $passengerId]);
            if ($query->fetchColumn() !== false) {
                throw new DomainException('This passenger already has a seat assignment.');
            }
            $query = $db->prepare('SELECT * FROM seats WHERE id = ? AND aircraft_id = ? FOR UPDATE');
            $query->execute([$seatId, $flight['aircraft_id']]);
            $seat = $query->fetch();
            if (!$seat || $seat['status'] !== 'active') {
                throw new DomainException('Select an active seat on the flight’s aircraft.');
            }
            $query = $db->prepare("SELECT id FROM booking_seats WHERE flight_id = ? AND occupied_seat_id = ? FOR UPDATE");
            $query->execute([$flight['id'], $seatId]);
            if ($query->fetchColumn() !== false) {
                throw new DomainException('This seat is already booked. Please choose another seat.');
            }
            $db->prepare("INSERT INTO booking_seats
                (booking_id, passenger_id, flight_id, aircraft_id, seat_id, status)
                VALUES (?, ?, ?, ?, ?, 'reserved')")
                ->execute([$bookingId, $passengerId, $flight['id'], $flight['aircraft_id'], $seatId]);
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('The seat or passenger already has an assignment. Reload the seat map.', 0, $exception);
            }
            throw $exception;
        }
    }
}
