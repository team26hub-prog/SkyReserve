<?php
declare(strict_types=1);

namespace App\Models;

use DomainException;
use OutOfBoundsException;
use PDOException;
use Throwable;

final class Ticket extends Model
{
    public function forBooking(int $bookingId): array
    {
        $query = $this->db()->prepare('SELECT t.*, bs.passenger_id FROM tickets t
            JOIN booking_seats bs ON bs.id = t.booking_seat_id WHERE bs.booking_id = ? ORDER BY t.id');
        $query->execute([$bookingId]);
        return $query->fetchAll();
    }

    public function findAuthorized(int $id, array $actor): ?array
    {
        $query = $this->db()->prepare("SELECT t.*, b.id AS booking_id, b.booking_reference,
            b.status AS booking_status, b.total_amount, b.currency,
            p.full_name, p.first_name, p.last_name, p.document_number,
            f.flight_number, f.departure_at, f.arrival_at,
            o.name AS origin_name, o.iata_code AS origin_code,
            d.name AS destination_name, d.iata_code AS destination_code,
            s.seat_number, s.cabin_class
            FROM tickets t JOIN booking_seats bs ON bs.id = t.booking_seat_id
            JOIN bookings b ON b.id = bs.booking_id
            JOIN passengers p ON p.id = bs.passenger_id AND p.booking_id = b.id
            JOIN flights f ON f.id = bs.flight_id AND f.id = b.flight_id
            JOIN seats s ON s.id = bs.seat_id AND s.aircraft_id = bs.aircraft_id
            JOIN airports o ON o.id = f.origin_airport_id
            JOIN airports d ON d.id = f.destination_airport_id
            JOIN users actor ON actor.id = ? AND actor.status = 'active'
            WHERE t.id = ? AND (actor.role = 'admin' OR (actor.role = 'customer' AND b.user_id = actor.id))");
        $query->execute([(int) ($actor['id'] ?? 0), $id]);
        return $query->fetch() ?: null;
    }

    public function generate(int $bookingId, array $actor): array
    {
        $db = $this->db();
        $query = $db->prepare("SELECT b.* FROM bookings b JOIN users actor ON actor.id = ? AND actor.status = 'active'
            WHERE b.id = ? AND (actor.role = 'admin' OR (actor.role = 'customer' AND b.user_id = actor.id))");
        $query->execute([(int) ($actor['id'] ?? 0), $bookingId]);
        $initial = $query->fetch();
        if (!$initial) throw new OutOfBoundsException('Booking not found.');
        $db->beginTransaction();
        try {
            // Match payment/seat lock order; read current rows after any competing writer finishes.
            $query = $db->prepare('SELECT * FROM flights WHERE id = ? FOR UPDATE');
            $query->execute([$initial['flight_id']]); $flight = $query->fetch();
            $query = $db->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
            $query->execute([$bookingId]); $booking = $query->fetch();
            $query = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active' FOR SHARE");
            $query->execute([(int) ($actor['id'] ?? 0)]); $user = $query->fetch();
            if (!$booking || !$user || !($user['role'] === 'admin' || ($user['role'] === 'customer' && (int) $booking['user_id'] === (int) $user['id']))) {
                throw new OutOfBoundsException('Booking not found.');
            }
            if (!$flight || (int) $booking['flight_id'] !== (int) $flight['id'] || $booking['status'] !== 'confirmed') {
                throw new DomainException('Tickets require a confirmed booking.');
            }
            if (!in_array($flight['status'], ['scheduled', 'delayed'], true) || $flight['departure_at'] <= gmdate('Y-m-d H:i:s')) {
                throw new DomainException('Tickets cannot be issued for an unavailable or departed flight.');
            }
            $query = $db->prepare('SELECT * FROM aircraft WHERE id = ? FOR UPDATE');
            $query->execute([$flight['aircraft_id']]); $aircraft = $query->fetch();
            if (!$aircraft || $aircraft['status'] !== 'active') throw new DomainException('The aircraft is unavailable.');
            $query = $db->prepare("SELECT id FROM payments WHERE booking_id = ? AND status = 'verified' FOR UPDATE");
            $query->execute([$bookingId]);
            if ($query->fetchColumn() === false) throw new DomainException('Tickets require a verified payment.');
            $query = $db->prepare('SELECT * FROM passengers WHERE booking_id = ? ORDER BY id FOR UPDATE');
            $query->execute([$bookingId]); $passengers = $query->fetchAll();
            if (!$passengers) throw new DomainException('Passenger details are required.');
            $allocations = [];
            foreach ($passengers as $passenger) {
                $name = $passenger['full_name'] ?? trim($passenger['first_name'] . ' ' . $passenger['last_name']);
                if ($passenger['status'] !== 'active' || trim($name) === '' || trim($passenger['document_number'] ?? '') === '') {
                    throw new DomainException('Every passenger needs valid active passenger details.');
                }
                $query = $db->prepare('SELECT * FROM booking_seats WHERE booking_id = ? AND passenger_id = ? FOR UPDATE');
                $query->execute([$bookingId, $passenger['id']]); $allocation = $query->fetch();
                if (!$allocation || !in_array($allocation['status'], ['reserved', 'confirmed'], true)
                    || (int) $allocation['flight_id'] !== (int) $flight['id'] || (int) $allocation['aircraft_id'] !== (int) $flight['aircraft_id']) {
                    throw new DomainException('Every passenger needs a valid seat on the booked flight.');
                }
                $query = $db->prepare('SELECT * FROM seats WHERE id = ? AND aircraft_id = ? FOR UPDATE');
                $query->execute([$allocation['seat_id'], $flight['aircraft_id']]); $seat = $query->fetch();
                if (!$seat || $seat['status'] !== 'active') throw new DomainException('The assigned seat is unavailable.');
                $query = $db->prepare('SELECT id FROM tickets WHERE booking_seat_id = ? FOR UPDATE');
                $query->execute([$allocation['id']]);
                if ($query->fetchColumn() !== false) throw new DomainException('A ticket has already been generated for this passenger.');
                $allocations[] = $allocation['id'];
            }
            $ids = [];
            foreach ($allocations as $allocationId) {
                for ($attempt = 0; $attempt < 5; ++$attempt) {
                    $number = 'SR-T-' . strtoupper(bin2hex(random_bytes(12)));
                    try {
                        $db->prepare('INSERT INTO tickets (booking_seat_id, ticket_number, issued_at) VALUES (?, ?, UTC_TIMESTAMP())')->execute([$allocationId, $number]);
                        $ids[] = (int) $db->lastInsertId(); break;
                    } catch (PDOException $exception) {
                        if ((int) ($exception->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) throw $exception;
                    }
                }
            }
            $db->commit(); return $ids;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }
}
