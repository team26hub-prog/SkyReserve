<?php
declare(strict_types=1);
namespace App\Models;
use DomainException;
use OutOfBoundsException;
use PDOException;
use Throwable;

final class Cancellation extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    public static function validText(string $value): bool
    {
        return mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= 1000 && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value);
    }
    public static function eligible(array $booking, ?array $flight): bool
    {
        return in_array($booking['status'], ['pending','payment_submitted','confirmed'], true)
            && ($booking['status'] === 'confirmed' || $booking['expires_at'] === null || $booking['expires_at'] > gmdate('Y-m-d H:i:s'))
            && $flight && in_array($flight['status'], ['scheduled','delayed'], true) && $flight['departure_at'] > gmdate('Y-m-d H:i:s');
    }
    public function forBooking(int $id): array
    {
        $query = $this->db()->prepare('SELECT c.*, u.name AS reviewer_name FROM cancellations c LEFT JOIN users u ON u.id = c.reviewed_by WHERE c.booking_id = ? ORDER BY c.id DESC');
        $query->execute([$id]); return $query->fetchAll();
    }
    public function pendingCount(): int { return (int) $this->db()->query("SELECT COUNT(*) FROM cancellations WHERE status = 'pending'")->fetchColumn(); }
    private function adminSelect(): string
    {
        return 'SELECT c.*, b.booking_reference, b.status AS booking_status, b.total_amount, b.currency, b.flight_id,
            u.name AS customer_name, u.email AS customer_email, f.flight_number, f.status AS flight_status, f.departure_at, f.arrival_at,
            o.iata_code AS origin_code, d.iata_code AS destination_code, reviewer.name AS reviewer_name
            FROM cancellations c JOIN bookings b ON b.id = c.booking_id JOIN users u ON u.id = b.user_id
            JOIN flights f ON f.id = b.flight_id JOIN airports o ON o.id = f.origin_airport_id
            JOIN airports d ON d.id = f.destination_airport_id LEFT JOIN users reviewer ON reviewer.id = c.reviewed_by';
    }
    public function listForAdmin(string $status): array
    {
        if (!isset(self::STATUSES[$status])) throw new DomainException('Choose a valid cancellation status.');
        $query = $this->db()->prepare($this->adminSelect() . ' WHERE c.status = ? ORDER BY c.id DESC'); $query->execute([$status]); return $query->fetchAll();
    }
    public function findForAdmin(int $id): ?array
    {
        $query = $this->db()->prepare($this->adminSelect() . ' WHERE c.id = ?'); $query->execute([$id]); return $query->fetch() ?: null;
    }
    public function request(int $bookingId, int $customerId, string $reason): int
    {
        if (!self::validText($reason)) throw new DomainException('Enter a reason of at most 1000 characters.');
        $initial = (new Booking())->findForCustomer($bookingId, $customerId);
        if (!$initial) throw new OutOfBoundsException('Booking not found.');
        $db = $this->db(); $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM flights WHERE id = ? FOR UPDATE'); $query->execute([$initial['flight_id']]); $flight = $query->fetch();
            $query = $db->prepare('SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE'); $query->execute([$bookingId,$customerId]); $booking = $query->fetch();
            $query = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'customer' AND status = 'active' FOR SHARE"); $query->execute([$customerId]);
            if (!$booking || $query->fetchColumn() === false) throw new OutOfBoundsException('Booking not found.');
            if ((int) $booking['flight_id'] !== (int) $initial['flight_id'] || !self::eligible($booking, $flight ?: null)) throw new DomainException('Cancellation is unavailable for this booking or flight.');
            $db->prepare('SELECT id FROM aircraft WHERE id = ? FOR UPDATE')->execute([$flight['aircraft_id']]);
            $query = $db->prepare("SELECT id FROM cancellations WHERE active_booking_id = ? FOR UPDATE"); $query->execute([$bookingId]);
            if ($query->fetchColumn() !== false) throw new DomainException('A cancellation request is already pending.');
            $query = $db->prepare("SELECT t.id FROM tickets t JOIN booking_seats bs ON bs.id = t.booking_seat_id WHERE bs.booking_id = ? AND t.status = 'used' FOR UPDATE"); $query->execute([$bookingId]);
            if ($query->fetchColumn() !== false) throw new DomainException('A used ticket cannot be cancelled.');
            $db->prepare('INSERT INTO cancellations (booking_id, requested_by, reason, previous_booking_status) VALUES (?, ?, ?, ?)')->execute([$bookingId,$customerId,trim($reason) === '' ? null : trim($reason),$booking['status']]);
            $id = (int) $db->lastInsertId();
            $db->prepare("UPDATE bookings SET status = 'cancellation_requested' WHERE id = ?")->execute([$bookingId]);
            $db->commit(); return $id;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) throw new DomainException('A cancellation request is already pending.', 0, $exception);
            throw $exception;
        }
    }
    public function review(int $id, int $adminId, string $decision, string $note = ''): void
    {
        if (!in_array($decision, ['approved','rejected'], true) || !self::validText($note)) throw new DomainException('Invalid cancellation review.');
        $initial = $this->findForAdmin($id); if (!$initial) throw new OutOfBoundsException('Cancellation not found.');
        $db = $this->db(); $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM flights WHERE id = ? FOR UPDATE'); $query->execute([$initial['flight_id']]); $flight = $query->fetch();
            $query = $db->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE'); $query->execute([$initial['booking_id']]); $booking = $query->fetch();
            if (!$flight || !$booking) throw new OutOfBoundsException('Booking not found.');
            if ((int) $booking['flight_id'] !== (int) $flight['id']) throw new DomainException('The booking changed. Reload the cancellation details.');
            $db->prepare('SELECT id FROM aircraft WHERE id = ? FOR UPDATE')->execute([$flight['aircraft_id']]);
            $query = $db->prepare('SELECT * FROM cancellations WHERE id = ? FOR UPDATE'); $query->execute([$id]); $request = $query->fetch();
            $query = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND status = 'active' FOR SHARE"); $query->execute([$adminId]);
            if ($query->fetchColumn() === false) throw new DomainException('An active administrator must review this request.');
            if (!$request) throw new OutOfBoundsException('Cancellation not found.');
            if ((int) $request['booking_id'] !== (int) $booking['id'] || $request['status'] !== 'pending' || $booking['status'] !== 'cancellation_requested'
                || !in_array($request['previous_booking_status'], ['pending','payment_submitted','confirmed'], true)) throw new DomainException('Only a pending request on a booking awaiting cancellation review can be reviewed.');
            if ($decision === 'approved') {
                if (!in_array($flight['status'], ['scheduled','delayed'], true) || $flight['departure_at'] <= gmdate('Y-m-d H:i:s')) throw new DomainException('A departed or unavailable flight cannot be cancelled here.');
                $query = $db->prepare("SELECT t.id FROM tickets t JOIN booking_seats bs ON bs.id = t.booking_seat_id WHERE bs.booking_id = ? AND t.status = 'used' FOR UPDATE"); $query->execute([$booking['id']]);
                if ($query->fetchColumn() !== false) throw new DomainException('A used ticket cannot be cancelled.');
                $db->prepare("UPDATE tickets t JOIN booking_seats bs ON bs.id = t.booking_seat_id SET t.status = 'void' WHERE bs.booking_id = ? AND t.status = 'valid'")->execute([$booking['id']]);
                $db->prepare("UPDATE booking_seats SET status = 'released' WHERE booking_id = ? AND status IN ('reserved','confirmed')")->execute([$booking['id']]);
            }
            $db->prepare('UPDATE cancellations SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(), review_notes = ? WHERE id = ?')->execute([$decision,$adminId,trim($note) === '' ? null : trim($note),$id]);
            $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$decision === 'approved' ? 'cancelled' : $request['previous_booking_status'],$booking['id']]);
            $db->commit();
        } catch (Throwable $exception) { if ($db->inTransaction()) $db->rollBack(); throw $exception; }
    }
}
