<?php use App\Models\Booking;
$section = $data['section'] ?? 'bookings';
$sectionDescriptions = ['bookings' => 'Review your bookings, payment progress, seats, and tickets.', 'seats' => 'Review your assigned seats or choose a seat for an upcoming booking.', 'payments' => 'Track payment progress and open a booking to submit payment or view its receipts.', 'tickets' => 'View and download your E-tickets. Open a confirmed booking to generate tickets after payment verification.']; ?>
<span class="eyebrow">Your journeys</span><h1><?= $escape($data['title'] ?? 'My Bookings') ?></h1>
<p class="muted"><?= $escape($sectionDescriptions[$section]) ?></p>
<?php if ($data['error']): ?><div class="notice error" role="alert"><?= $escape($data['error']) ?></div><?php endif; ?>
<nav class="filter-tabs" aria-label="Booking status filters">
    <?php foreach (['all' => 'All'] + Booking::STATUSES as $status => $label): ?><a href="/bookings?section=<?= $escape($section) ?>&amp;status=<?= $status ?>"<?= $data['status'] === $status ? ' aria-current="page"' : '' ?>><?= $escape($label) ?></a><?php endforeach; ?>
</nav>
<?php if (!$data['bookings']): ?><p class="empty-state">No bookings match this filter. <a href="/flights">Search flights</a> to plan a journey.</p><?php endif; ?>
<div class="flight-results">
<?php foreach ($data['bookings'] as $booking): ?>
    <article class="flight-card">
        <div class="page-heading"><h2><?= $escape($booking['booking_reference']) ?></h2><span class="badge" data-status="<?= $escape($booking['status']) ?>"><?= $escape(Booking::statusLabel($booking['status'])) ?></span></div>
        <p><strong><?= $escape($booking['flight_number'] . ' · ' . $booking['origin_code'] . ' → ' . $booking['destination_code']) ?></strong></p>
        <dl class="profile-details">
            <dt>Departure (UTC)</dt><dd><?= $escape($booking['departure_at']) ?><small class="cell-note">Flight <?= $escape(ucfirst($booking['flight_status'])) ?></small></dd>
            <dt>Passenger / seat</dt><dd><?php foreach ($booking['passengers'] as $passenger): ?>
                <div><?= $escape($passenger['full_name'] ?? trim($passenger['first_name'] . ' ' . $passenger['last_name'])) ?>
                    <?php $assigned = array_filter($booking['assignments'], static fn (array $seat): bool => (int) $seat['passenger_id'] === (int) $passenger['id'] && in_array($seat['status'], ['reserved','confirmed'], true)); ?>
                    <small class="cell-note"><?php if ($assigned): $seat = reset($assigned); ?>Seat <?= $escape($seat['seat_number'] . ' / ' . ucfirst($seat['cabin_class'])) ?><?php else: ?>No active seat assigned<?php endif; ?></small>
                </div><?php endforeach; ?><?php if (!$booking['passengers']): ?>No passenger recorded<?php endif; ?></dd>
            <dt>Fare at booking</dt><dd><?= $escape($booking['currency'] . ' ' . $booking['total_amount']) ?></dd>
            <dt>Latest payment status</dt><dd><?= $booking['payment_status'] ? $escape(ucfirst($booking['payment_status'])) : 'Not submitted' ?></dd>
        </dl>
        <div class="actions"><a class="button" href="/bookings/show?id=<?= (int) $booking['id'] ?>">View booking</a>
        <?php $canViewSeats = in_array($booking['status'], ['pending', 'payment_submitted', 'confirmed'], true) && in_array($booking['flight_status'], ['scheduled', 'delayed'], true) && $booking['departure_at'] > gmdate('Y-m-d H:i:s') && ($booking['status'] === 'confirmed' || $booking['expires_at'] === null || $booking['expires_at'] > gmdate('Y-m-d H:i:s')); ?>
        <?php if ($section === 'seats' && $canViewSeats): ?><a class="button button-secondary" href="/bookings/seats?booking_id=<?= (int) $booking['id'] ?>">View / select seats</a><?php endif; ?>
        <?php if ($section === 'payments'): ?><a class="button button-secondary" href="/bookings/show?id=<?= (int) $booking['id'] ?>#payment-details">Payment details</a><?php endif; ?>
        <?php if ($section === 'tickets' && !$booking['tickets']): ?><small>No E-tickets issued for this booking yet.</small><?php endif; ?>
        <?php foreach ($booking['tickets'] as $ticket): ?><a class="button button-secondary" href="/tickets/show?id=<?= (int) $ticket['id'] ?>">View <?= $escape($ticket['status']) ?> ticket</a><?php endforeach; ?></div>
    </article>
<?php endforeach; ?>
</div>
