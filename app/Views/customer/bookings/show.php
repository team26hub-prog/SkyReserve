<?php use App\Models\Payment; $booking = $data['booking']; $flight = $data['flight']; ?>
<span class="eyebrow">Your journey at a glance</span>
<h1>Booking summary</h1>
<dl class="profile-details">
    <dt>Booking reference / PNR</dt><dd><strong><?= $escape($booking['booking_reference']) ?></strong></dd>
    <dt>Booking status</dt><dd><span class="badge" data-status="<?= $escape($booking['status']) ?>"><?= match ($booking['status']) { 'pending' => 'Pending Payment', 'payment_submitted' => 'Payment Submitted / Awaiting Verification', default => $escape(ucfirst($booking['status'])) } ?></span></dd>
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
        <dt>CNIC / Passport</dt><dd><?= $escape($passenger['document_number'] ?? '') ?></dd>
        <dt>Date of birth</dt><dd><?= $escape($passenger['date_of_birth'] ?? '') ?></dd>
        <dt>Gender</dt><dd><?= $escape(ucfirst($passenger['gender'] ?? '')) ?></dd>
        <dt>Phone</dt><dd><?= $escape($passenger['phone'] ?? '') ?></dd>
    </dl>
<?php endforeach; ?>
<p><a href="/flights">Search flights</a></p>
<?php if (in_array($booking['status'], ['pending', 'payment_submitted', 'confirmed'], true) && in_array($flight['status'], ['scheduled', 'delayed'], true) && $flight['departure_at'] > gmdate('Y-m-d H:i:s')): ?>
    <p><a class="button" href="/bookings/seats?booking_id=<?= (int) $booking['id'] ?>">View / select seats</a></p>
<?php endif; ?>
<?php if ($data['payments']): ?>
    <h2>Payment details</h2>
    <?php foreach ($data['payments'] as $payment): ?>
        <dl class="profile-details">
            <dt>Payment method</dt><dd><?= $escape(Payment::METHODS[$payment['method']] ?? ucfirst($payment['method'])) ?></dd>
            <dt>Amount paid</dt><dd><?= $escape($payment['currency'] . ' ' . $payment['amount']) ?></dd>
            <dt>Transaction reference</dt><dd><?= $escape($payment['transaction_reference'] ?? 'Not provided') ?></dd>
            <dt>Payment status</dt><dd><span class="badge" data-status="<?= $escape($payment['status']) ?>"><?= $escape(ucfirst($payment['status'])) ?></span></dd>
            <dt>Payment date (UTC)</dt><dd><?= $escape($payment['payment_date'] ?? 'Not provided') ?></dd>
            <dt>Receipt</dt><dd><?php if ($payment['proof_path']): ?><a href="/payments/receipt?id=<?= (int) $payment['id'] ?>">View receipt</a><?php else: ?>No receipt uploaded<?php endif; ?></dd>
            <?php if ($payment['status'] === 'rejected' && $payment['review_notes']): ?><dt>Rejection reason</dt><dd><?= nl2br($escape($payment['review_notes'])) ?></dd><?php endif; ?>
        </dl>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($booking['status'] === 'pending' && !array_filter($data['payments'], static fn (array $payment): bool => in_array($payment['status'], ['pending', 'verified'], true))): ?>
    <p><a class="button" href="/bookings/payment?booking_id=<?= (int) $booking['id'] ?>">Submit payment</a></p>
<?php endif; ?>
<?php
$ticketPrefix = ''; $ticketBookingId = (int) $booking['id'];
$ticketEligible = $booking['status'] === 'confirmed' && (bool) array_filter($data['payments'], static fn (array $payment): bool => $payment['status'] === 'verified');
require BASE_PATH . '/app/Views/tickets/booking-links.php';
?>
