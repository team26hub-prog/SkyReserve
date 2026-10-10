<?php use App\Models\Booking; $booking = $data['booking']; $flight = $data['flight']; ?>
<span class="eyebrow">Your booking</span><h1>Request cancellation</h1>
<dl class="profile-details"><dt>Booking / PNR</dt><dd><?= $escape($booking['booking_reference']) ?></dd><dt>Flight / route</dt><dd><?= $escape($flight['flight_number'] . ' · ' . $flight['origin_code'] . ' → ' . $flight['destination_code']) ?></dd><dt>Departure (UTC)</dt><dd><?= $escape($flight['departure_at']) ?></dd><dt>Booking status</dt><dd><?= $escape(Booking::statusLabel($booking['status'])) ?></dd></dl>
<p class="notice">Your request needs admin approval. Seats remain held while review is pending. Approval cancels the booking, releases seats, and voids tickets. Payments are handled manually; this request does not initiate a refund.</p>
<?php if ($data['error']): ?><p class="notice error" role="alert"><?= $escape($data['error']) ?></p><?php endif; ?>
<form class="account-form" method="post" action="/bookings/cancel?booking_id=<?= (int) $booking['id'] ?>" data-confirm="Request cancellation of this booking? An administrator will review it.">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="reason">Cancellation reason <span class="muted">(optional)</span></label><textarea id="reason" name="reason" rows="4" maxlength="1000" aria-describedby="reason-help"><?= $escape($data['reason']) ?></textarea><small id="reason-help">Up to 1000 characters.</small>
    <div class="actions form-actions">
        <button class="button-danger" type="submit">Confirm cancellation request</button>
        <a class="button button-secondary" href="/bookings/show?id=<?= (int) $booking['id'] ?>">Keep booking / return to summary</a>
    </div>
</form>
