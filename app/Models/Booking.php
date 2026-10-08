<?php
declare(strict_types=1);
namespace App\Models;
use DomainException;
use PDOException;
use Throwable;
final class Booking extends Model
{
    public function findForCustomer(int $id, int $customerId): ?array
    {
        $query = $this->db()->prepare('SELECT * FROM bookings WHERE id = ? AND user_id = ?');
        $query->execute([$id, $customerId]);
        return $query->fetch() ?: null;
    }
    public function submitted(int $customerId, string $key): ?array
    {
        $query = $this->db()->prepare('SELECT * FROM bookings WHERE submission_key = ? AND user_id = ?');
        $query->execute([$key, $customerId]);
        return $query->fetch() ?: null;
    }
    public function create(int $customerId, int $flightId, string $key, array $passenger): int
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // Lock the flight against concurrent admin edits while rechecking availability.
            $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE')->execute([$flightId]);
            if ($existing = $this->submitted($customerId, $key)) {
                if ((int) $existing['flight_id'] !== $flightId) { throw new DomainException('This booking form belongs to a different flight.'); }
                $db->commit();
                return (int) $existing['id'];
            }
            $flight = (new Flight())->findAvailable($flightId);
            if (!$flight) { throw new DomainException('This flight is no longer scheduled and available. Please choose another flight.'); }
            // PNR uniqueness is enforced by the existing unique index; retry a collision.
            for ($attempt = 0; $attempt < 5; ++$attempt) {
                try {
                    $db->prepare("INSERT INTO bookings (booking_reference, submission_key, user_id, flight_id, total_amount, currency, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')")->execute([
                        'SR' . strtoupper(bin2hex(random_bytes(6))), $key, $customerId, $flightId, $flight['base_fare'], $flight['currency'],
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ((int) ($exception->errorInfo[1] ?? 0) !== 1062 || $attempt === 4) { throw $exception; }
                }
            }
            $id = (int) $db->lastInsertId();
            (new Passenger())->create($id, $passenger);
            $db->commit();
            return $id;
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }
}
