<?php
use App\Models\AdminReport;
use App\Models\Booking;
$recentBookings = $data['recentBookings'];
?>
<section class="recent-bookings" aria-labelledby="recent-bookings-title">
    <div class="recent-bookings-heading">
        <div><h2 id="recent-bookings-title">Recent Customer Bookings</h2><p class="muted">Latest 10 bookings · Booking times in UTC</p></div>
        <a class="button button-secondary" href="/admin/reports/bookings">View all bookings →</a>
    </div>
    <?php if (!$recentBookings): ?>
        <div class="empty-state"><h3>No customer bookings yet</h3><p>New bookings will appear here with customer, passenger and payment details.</p></div>
    <?php else: ?>
        <div class="table-scroll recent-bookings-scroll" tabindex="0" role="region" aria-label="Recent customer bookings — scroll horizontally to see all columns">
        <table class="recent-bookings-table" role="table">
            <caption class="sr-only">Latest customer bookings, newest first. Seats shown are currently assigned.</caption>
            <thead role="rowgroup"><tr role="row">
                <th scope="col" role="columnheader">Booking / date</th><th scope="col" role="columnheader">Customer</th><th scope="col" role="columnheader">Flight / route</th>
                <th scope="col" role="columnheader">Passengers / seats</th><th scope="col" role="columnheader">Payment status</th><th scope="col" role="columnheader">Booking status</th>
            </tr></thead>
            <tbody role="rowgroup">
            <?php foreach ($recentBookings as $booking): ?>
                <tr role="row" data-recent-booking="<?= (int) $booking['id'] ?>">
                    <td role="cell" class="recent-booking-reference"><span class="recent-cell-label" aria-hidden="true">Booking / date</span>
                        <strong><?= $escape($booking['booking_reference']) ?></strong>
                        <time class="cell-note" datetime="<?= $escape(str_replace(' ', 'T', $booking['created_at']) . 'Z') ?>"><?= $escape($booking['created_at']) ?> UTC</time>
                    </td>
                    <td role="cell" class="recent-booking-customer"><span class="recent-cell-label" aria-hidden="true">Customer</span>
                        <strong><?= $escape($booking['customer']) ?></strong><small class="cell-note"><?= $escape($booking['customer_email']) ?></small>
                    </td>
                    <td role="cell"><span class="recent-cell-label" aria-hidden="true">Flight / route</span>
                        <strong><?= $escape($booking['flight_number']) ?></strong><span class="cell-note"><?= $escape($booking['origin_code'] . ' → ' . $booking['destination_code']) ?></span>
                    </td>
                    <td role="cell" class="recent-booking-passengers"><span class="recent-cell-label" aria-hidden="true">Passengers / seats</span>
                        <?php if (!$booking['passengers']): ?><span class="muted">No passengers recorded</span><?php else: ?>
                        <ul class="recent-passenger-list">
                            <?php foreach ($booking['passengers'] as $passenger): ?><li>
                                <span><?= $escape($passenger['name']) ?></span>
                                <small class="cell-note"><?= $passenger['seat_number'] === null ? 'Seat not assigned' : $escape($passenger['seat_number'] . ' · ' . ucfirst($passenger['cabin_class'])) ?></small>
                            </li><?php endforeach; ?>
                        </ul><?php endif; ?>
                    </td>
                    <td role="cell"><span class="recent-cell-label" aria-hidden="true">Payment status</span>
                        <span class="badge" data-status="<?= $escape($booking['payment_status'] ?? 'not_submitted') ?>"><?= $escape(AdminReport::PAYMENT_STATUSES[$booking['payment_status']] ?? 'Not submitted') ?></span>
                        <?php if ($booking['payment_id'] !== null): ?><a class="button button-secondary recent-booking-action" href="/admin/payments/show?id=<?= (int) $booking['payment_id'] ?>" aria-label="<?= $escape('View payment for booking ' . $booking['booking_reference']) ?>">View payment</a><?php endif; ?>
                    </td>
                    <td role="cell"><span class="recent-cell-label" aria-hidden="true">Booking status</span>
                        <span class="badge" data-status="<?= $escape($booking['booking_status']) ?>"><?= $escape(Booking::statusLabel($booking['booking_status'])) ?></span>
                        <a class="button button-secondary recent-booking-action" href="/admin/reports/passengers?flight_id=<?= (int) $booking['flight_id'] ?>" aria-label="<?= $escape('View flight passenger report for booking ' . $booking['booking_reference']) ?>">View passengers</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
