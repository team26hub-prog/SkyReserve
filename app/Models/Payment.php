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

    private function validateBooking(array $booking, ?array $flight): void
    {
        if ($booking['status'] !== 'pending'
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
