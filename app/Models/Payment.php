<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\PaymentReceipt;
use DateTimeImmutable;
use DomainException;
use OutOfBoundsException;
use PDOException;
use Throwable;

final class Payment extends Model
{
    public const METHODS = ['bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other'];
    public const REVIEW_STATUSES = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'];

    public function pendingCount(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
    }

    private function adminSelect(): string
    {
        return 'SELECT p.*, b.booking_reference, b.status AS booking_status,
            b.total_amount AS amount_due, b.currency AS booking_currency, b.expires_at,
            b.flight_id, u.name AS customer_name, u.email AS customer_email,
            f.flight_number, f.status AS flight_status, f.departure_at, f.arrival_at,
            o.iata_code AS origin_code, d.iata_code AS destination_code,
            reviewer.name AS reviewer_name
            FROM payments p JOIN bookings b ON b.id = p.booking_id
            JOIN users u ON u.id = b.user_id JOIN flights f ON f.id = b.flight_id
            JOIN airports o ON o.id = f.origin_airport_id
            JOIN airports d ON d.id = f.destination_airport_id
            LEFT JOIN users reviewer ON reviewer.id = p.reviewed_by';
    }

    public function listForAdmin(string $status): array
    {
        if (!isset(self::REVIEW_STATUSES[$status])) throw new DomainException('Choose a valid payment status.');
        $query = $this->db()->prepare($this->adminSelect() . ' WHERE p.status = ? ORDER BY p.created_at DESC, p.id DESC');
        $query->execute([$status]);
        return $query->fetchAll();
    }

    public function findForAdmin(int $id): ?array
    {
        $query = $this->db()->prepare($this->adminSelect() . ' WHERE p.id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public static function validReviewReason(string $reason): bool
    {
        return mb_check_encoding($reason, 'UTF-8') && mb_strlen($reason) <= 1000
            && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason);
    }

    public function review(int $paymentId, int $adminId, string $decision, string $reason = ''): void
    {
        if (!in_array($decision, ['verified', 'rejected'], true) || !self::validReviewReason($reason)) {
            throw new DomainException('Invalid payment review details.');
        }
        // Resolve IDs before starting the transaction to avoid a stale read snapshot.
        $initial = $this->findForAdmin($paymentId);
        if (!$initial) throw new OutOfBoundsException('Payment not found.');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $query = $db->prepare('SELECT * FROM flights WHERE id = ? FOR UPDATE');
            $query->execute([$initial['flight_id']]);
            $flight = $query->fetch();
            $query = $db->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
            $query->execute([$initial['booking_id']]);
            $booking = $query->fetch();
            if (!$flight || !$booking) throw new OutOfBoundsException('Booking or flight not found.');
            if ((int) $booking['flight_id'] !== (int) $initial['flight_id']) {
                throw new DomainException('The booking changed. Reload the payment details.');
            }
            // Match the flight -> booking -> aircraft -> payment order used by submission.
            $db->prepare('SELECT id FROM aircraft WHERE id = ? FOR UPDATE')->execute([$flight['aircraft_id']]);
            $query = $db->prepare('SELECT * FROM payments WHERE id = ? FOR UPDATE');
            $query->execute([$paymentId]);
            $payment = $query->fetch();
            if (!$payment) throw new OutOfBoundsException('Payment not found.');
            if ((int) $payment['booking_id'] !== (int) $booking['id']) {
                throw new DomainException('The payment changed. Reload the payment details.');
            }
            $query = $db->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' AND status = 'active' FOR SHARE");
            $query->execute([$adminId]);
            if ($query->fetchColumn() === false) throw new DomainException('An active administrator must review this payment.');
            if ($payment['status'] !== 'pending' || $booking['status'] !== 'payment_submitted') {
                throw new DomainException('Only a pending payment on a booking awaiting verification can be reviewed.');
            }
            if ($decision === 'verified') {
                // Verification must not confirm an expired booking or unavailable/past flight.
                $this->validateBooking($booking, $flight, 'payment_submitted');
            }
            $query = $db->prepare('UPDATE payments SET status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP(), review_notes = ? WHERE id = ?');
            $query->execute([$decision, $adminId, $decision === 'rejected' && $reason !== '' ? $reason : null, $paymentId]);
            $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$decision === 'verified' ? 'confirmed' : 'pending', $booking['id']]);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            throw $exception;
        }
    }

    public function forBooking(int $bookingId): array
    {
        $query = $this->db()->prepare('SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC, id DESC');
        $query->execute([$bookingId]);
        return $query->fetchAll();
    }

    public function receiptForCustomer(int $paymentId, int $customerId): ?array
    {
        $query = $this->db()->prepare('SELECT p.* FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE p.id = ? AND b.user_id = ?');
        $query->execute([$paymentId, $customerId]);
        return $query->fetch() ?: null;
    }

    public function context(int $bookingId, int $customerId): array
    {
        $booking = (new Booking())->findForCustomer($bookingId, $customerId);
        if (!$booking) throw new OutOfBoundsException('Booking not found.');
        $flight = (new Flight())->find((int) $booking['flight_id']);
        $this->validateBooking($booking, $flight);
        $this->checkActivePayment($bookingId);
        return ['booking' => $booking, 'flight' => $flight];
    }

    private function validateBooking(array $booking, ?array $flight, string $requiredStatus = 'pending'): void
    {
        if ($booking['status'] !== $requiredStatus
            || ($booking['expires_at'] !== null && $booking['expires_at'] <= gmdate('Y-m-d H:i:s'))
            || !$flight || !in_array($flight['status'], ['scheduled', 'delayed'], true)
            || $flight['departure_at'] <= gmdate('Y-m-d H:i:s')) {
            throw new DomainException('This booking is no longer awaiting payment or its flight is unavailable.');
        }
        $query = $this->db()->prepare("SELECT COUNT(*) FROM aircraft a
            JOIN airports o ON o.id = ? JOIN airports d ON d.id = ?
            WHERE a.id = ? AND a.status = 'active' AND o.status = 'active' AND d.status = 'active'");
        $query->execute([$flight['origin_airport_id'], $flight['destination_airport_id'], $flight['aircraft_id']]);
        if (!(int) $query->fetchColumn()) throw new DomainException('The flight aircraft or route is unavailable.');
    }

    private function checkActivePayment(int $bookingId, bool $lock = false): void
    {
        $query = $this->db()->prepare('SELECT id FROM payments WHERE active_booking_id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$bookingId]);
        if ($query->fetchColumn() !== false) throw new DomainException('Payment has already been submitted for this booking.');
    }

    public static function errors(array $values): array
    {
        $errors = [];
        if (!isset(self::METHODS[$values['method'] ?? ''])) $errors[] = 'Select a payment method.';
        $amount = $values['amount'] ?? '';
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $amount) || str_replace(['0', '.'], '', $amount) === '') {
            $errors[] = 'Enter a positive amount with at most two decimal places, up to 9999999999.99.';
        }
        if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9 ._:\/-]{0,119}\z/', $values['transaction_reference'] ?? '')) {
            $errors[] = 'Enter a transaction/reference number of 1–120 letters, digits, spaces, or . _ : / - characters.';
        }
        $date = $values['payment_date'] ?? '';
        $parsed = preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1900-01-01' || $date > gmdate('Y-m-d')) {
            $errors[] = 'Enter a valid payment date from 1900 through today (UTC).';
        }
        return $errors;
    }

    public function submit(int $bookingId, int $customerId, array $values, ?array $upload): int
    {
        if (self::errors($values)) throw new DomainException('Check your payment details and try again.');
        $initial = (new Booking())->findForCustomer($bookingId, $customerId);
        if (!$initial) throw new OutOfBoundsException('Booking not found.');
        $db = $this->db();
        $receipts = new PaymentReceipt();
        $relative = null;
        $db->beginTransaction();
        try {
            // Same flight -> booking -> aircraft lock order as customer seat selection.
            $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE')->execute([$initial['flight_id']]);
            $query = $db->prepare('SELECT * FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE');
            $query->execute([$bookingId, $customerId]);
            $booking = $query->fetch();
            if (!$booking) throw new OutOfBoundsException('Booking not found.');
            if ((int) $booking['flight_id'] !== (int) $initial['flight_id']) throw new DomainException('The booking changed. Reload this page.');
            $query = $db->prepare('SELECT aircraft_id FROM flights WHERE id = ? FOR UPDATE');
            $query->execute([$booking['flight_id']]);
            $db->prepare('SELECT id FROM aircraft WHERE id = ? FOR UPDATE')->execute([$query->fetchColumn()]);
            $this->validateBooking($booking, (new Flight())->find((int) $booking['flight_id']));
            $this->checkActivePayment($bookingId, true);
            $relative = $receipts->save($upload);
            $query = $db->prepare("INSERT INTO payments
                (booking_id, amount, currency, method, transaction_reference, payment_date, proof_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $query->execute([$bookingId, $values['amount'], $booking['currency'], $values['method'], $values['transaction_reference'], $values['payment_date'], $relative]);
            $id = (int) $db->lastInsertId();
            $db->prepare("UPDATE bookings SET status = 'payment_submitted' WHERE id = ?")->execute([$bookingId]);
            $db->commit();
            return $id;
        } catch (Throwable $exception) {
            try { if ($db->inTransaction()) $db->rollBack(); }
            finally { $receipts->remove($relative); }
            if ($exception instanceof PDOException && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Payment has already been submitted for this booking.', 0, $exception);
            }
            throw $exception;
        }
    }
}
