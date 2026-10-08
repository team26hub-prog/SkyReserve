<?php use App\Models\Booking; ?>
<span class="eyebrow">Your journeys</span><h1>My Bookings</h1>
<p class="muted">Review your bookings, payment progress, seats, and tickets.</p>
<?php if ($data['error']): ?><div class="notice error" role="alert"><?= $escape($data['error']) ?></div><?php endif; ?>
<nav class="filter-tabs" aria-label="Booking status filters">
    <?php foreach (['all' => 'All'] + Booking::STATUSES as $status => $label): ?><a href="/bookings?status=<?= $status ?>"<?= $data['status'] === $status ? ' aria-current="page"' : '' ?>><?= $escape($label) ?></a><?php endforeach; ?>
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
        <?php foreach ($booking['tickets'] as $ticket): ?><a href="/tickets/show?id=<?= (int) $ticket['id'] ?>">View <?= $escape($ticket['status']) ?> ticket</a><?php endforeach; ?></div>
    </article>
<?php endforeach; ?>
</div>
