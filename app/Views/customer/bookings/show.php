<?php use App\Models\Payment; use App\Models\Booking; use App\Models\Cancellation; $booking = $data['booking']; $flight = $data['flight']; ?>
<span class="eyebrow">Your journey at a glance</span>
<h1>Booking summary</h1>
<dl class="profile-details">
    <dt>Booking reference / PNR</dt><dd><strong><?= $escape($booking['booking_reference']) ?></strong></dd>
    <dt>Booking status</dt><dd><span class="badge" data-status="<?= $escape($booking['status']) ?>"><?= $escape(Booking::statusLabel($booking['status'])) ?></span></dd>
    <dt>Flight</dt><dd><?= $escape($flight['flight_number']) ?></dd>
    <dt>Route</dt><dd><?= $escape($flight['origin_name'] . ' (' . $flight['origin_code'] . ') → ' . $flight['destination_name'] . ' (' . $flight['destination_code'] . ')') ?></dd>
    <dt>Departure (UTC)</dt><dd><?= $escape($flight['departure_at']) ?></dd>
    <dt>Arrival (UTC)</dt><dd><?= $escape($flight['arrival_at']) ?></dd>
    <dt>Fare at booking</dt><dd><?= $escape($booking['currency'] . ' ' . $booking['total_amount']) ?></dd>
</dl>
<?php foreach ($data['passengers'] as $passenger): ?>
    <h2>Passenger</h2><dl class="profile-details">
        <?php $selected = null; foreach ($data['assignments'] as $assignment) { if ((int) $assignment['passenger_id'] === (int) $passenger['id'] && in_array($assignment['status'], ['reserved', 'confirmed'], true)) { $selected = $assignment; break; } } ?>
        <dt>Selected seat</dt><dd><?= $selected ? $escape($selected['seat_number'] . ' — ' . ucfirst($selected['cabin_class'])) : 'Not selected' ?></dd>
        <dt>Full name</dt><dd><?= $escape($passenger['full_name'] ?? trim($passenger['first_name'] . ' ' . $passenger['last_name'])) ?></dd>
        <?php
        // Legacy bookings may have only the original combined document field.
        $legacyDocument = $passenger['document_number'] ?? '';
        $cnic = $passenger['cnic'] ?? (preg_match('/\A(?:[0-9]{13}|[0-9]{5}-[0-9]{7}-[0-9])\z/', $legacyDocument) ? str_replace('-', '', $legacyDocument) : '');
        $passport = $passenger['passport_number'] ?? ($cnic === '' ? $legacyDocument : '');
        $formattedCnic = preg_match('/\A[0-9]{13}\z/', $cnic) ? substr($cnic, 0, 5) . '-' . substr($cnic, 5, 7) . '-' . substr($cnic, 12) : $cnic;
        ?>
        <dt>CNIC</dt><dd><?= $escape($formattedCnic ?: 'Not provided') ?></dd>
        <dt>Passport number</dt><dd><?= $escape($passport ?: 'Not provided') ?></dd>
        <dt>Date of birth</dt><dd><?= $escape($passenger['date_of_birth'] ?? '') ?></dd>
        <dt>Gender</dt><dd><?= $escape(ucfirst($passenger['gender'] ?? '')) ?></dd>
        <dt>Phone</dt><dd><?= $escape($passenger['phone'] ?? '') ?></dd>
    </dl>
<?php endforeach; ?>
<div class="actions">
<a class="button button-secondary" href="/flights">Search flights</a>
<?php $withinDeadline = $booking['status'] === 'confirmed' || $booking['expires_at'] === null || $booking['expires_at'] > gmdate('Y-m-d H:i:s');
$upcomingFlight = in_array($flight['status'], ['scheduled', 'delayed'], true) && $flight['departure_at'] > gmdate('Y-m-d H:i:s'); ?>
<?php if ($withinDeadline && $upcomingFlight && in_array($booking['status'], ['pending', 'payment_submitted', 'confirmed'], true)): ?>
    <p><a class="button" href="/bookings/seats?booking_id=<?= (int) $booking['id'] ?>">View / select seats</a></p>
<?php endif; ?>
<?php if ($withinDeadline && $upcomingFlight && $booking['status'] === 'pending' && !array_filter($data['payments'], static fn (array $payment): bool => in_array($payment['status'], ['pending', 'verified'], true))): ?>
    <a class="button" href="/bookings/payment?booking_id=<?= (int) $booking['id'] ?>">Submit payment</a>
<?php endif; ?>
</div>
<div id="payment-details"></div>
<?php if ($data['payments']): ?>
    <h2>Payment details</h2>
    <?php foreach ($data['payments'] as $payment): ?>
        <dl class="profile-details">
            <dt>Payment method</dt><dd><?= $escape(Payment::METHODS[$payment['method']] ?? ucfirst($payment['method'])) ?></dd>
            <dt>Amount paid</dt><dd><?= $escape($payment['currency'] . ' ' . $payment['amount']) ?></dd>
            <dt>Transaction reference</dt><dd><?= $escape($payment['transaction_reference'] ?? 'Not provided') ?></dd>
            <dt>Payment status</dt><dd><span class="badge" data-status="<?= $escape($payment['status']) ?>"><?= $escape(ucfirst($payment['status'])) ?></span></dd>
            <dt>Payment date (UTC)</dt><dd><?= $escape($payment['payment_date'] ?? 'Not provided') ?></dd>
            <dt>Receipt</dt><dd><?php if ($payment['proof_path']): ?><a class="button button-secondary" href="/payments/receipt?id=<?= (int) $payment['id'] ?>">View receipt</a><?php else: ?>No receipt uploaded<?php endif; ?></dd>
            <?php if ($payment['status'] === 'rejected' && $payment['review_notes']): ?><dt>Rejection reason</dt><dd><?= nl2br($escape($payment['review_notes'])) ?></dd><?php endif; ?>
        </dl>
    <?php endforeach; ?>
<?php endif; ?>
<?php
$ticketPrefix = ''; $ticketBookingId = (int) $booking['id'];
$ticketEligible = $booking['status'] === 'confirmed' && (bool) array_filter($data['payments'], static fn (array $payment): bool => $payment['status'] === 'verified');
require BASE_PATH . '/app/Views/tickets/booking-links.php';
?>
<?php require BASE_PATH . '/app/Views/customer/cancellations/history.php'; ?>
<div class="actions">
<?php if (Cancellation::eligible($booking, $flight)): ?><p><a class="button button-danger" href="/bookings/cancel?booking_id=<?= (int) $booking['id'] ?>">Request cancellation</a></p><?php endif; ?>
<p><a class="button button-secondary" href="/bookings">Return to My Bookings</a></p>
</div>
