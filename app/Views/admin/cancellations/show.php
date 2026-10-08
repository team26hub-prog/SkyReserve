<?php use App\Models\Booking; $request = $data['request']; ?>
<span class="eyebrow">Operations · Cancellation review</span><div class="page-heading"><h1>Cancellation details</h1><span class="badge" data-status="<?= $escape($request['status']) ?>"><?= $escape(ucfirst($request['status'])) ?></span></div>
<?php if ($data['error']): ?><p class="notice error" role="alert"><?= $escape($data['error']) ?></p><?php endif; ?>
<dl class="profile-details">
    <dt>Customer</dt><dd><?= $escape($request['customer_name']) ?><small class="cell-note"><?= $escape($request['customer_email']) ?></small></dd>
    <dt>Booking / PNR</dt><dd><?= $escape($request['booking_reference']) ?></dd><dt>Booking status</dt><dd><?= $escape(Booking::statusLabel($request['booking_status'])) ?></dd>
    <dt>Previous booking status</dt><dd><?= $escape(Booking::statusLabel($request['previous_booking_status'] ?? 'Not recorded')) ?></dd>
    <dt>Flight / route</dt><dd><?= $escape($request['flight_number'] . ' · ' . $request['origin_code'] . ' → ' . $request['destination_code']) ?></dd>
    <dt>Departure / arrival (UTC)</dt><dd><?= $escape($request['departure_at'] . ' / ' . $request['arrival_at']) ?></dd>
    <dt>Fare at booking</dt><dd><?= $escape($request['currency'] . ' ' . $request['total_amount']) ?></dd>
    <dt>Requested at (UTC)</dt><dd><?= $escape($request['created_at']) ?></dd><dt>Reason</dt><dd><?= nl2br($escape($request['reason'] ?? 'No reason provided')) ?></dd>
    <dt>Reviewer</dt><dd><?= $escape($request['reviewer_name'] ?? 'Awaiting review') ?></dd><dt>Reviewed at (UTC)</dt><dd><?= $escape($request['reviewed_at'] ?? 'Awaiting review') ?></dd><dt>Admin note</dt><dd><?= nl2br($escape($request['review_notes'] ?? 'No note provided')) ?></dd>
</dl>
<h2>Passengers and seats</h2>
<?php foreach ($data['passengers'] as $passenger): ?><p><?= $escape($passenger['full_name'] ?? trim($passenger['first_name'] . ' ' . $passenger['last_name'])) ?> · <?= $escape($passenger['document_number'] ?? '') ?></p><?php endforeach; ?>
<?php foreach ($data['assignments'] as $seat): ?><p>Seat <?= $escape($seat['seat_number'] . ' / ' . ucfirst($seat['cabin_class']) . ' · ' . ucfirst($seat['status'])) ?></p><?php endforeach; ?>
<h2>Payments and tickets</h2>
<?php foreach ($data['payments'] as $payment): ?><p><a href="/admin/payments/show?id=<?= (int) $payment['id'] ?>"><?= $escape($payment['currency'] . ' ' . $payment['amount'] . ' · ' . ucfirst($payment['status'])) ?> payment</a></p><?php endforeach; ?>
<?php foreach ($data['tickets'] as $ticket): ?><p><a href="/admin/tickets/show?id=<?= (int) $ticket['id'] ?>"><?= $escape($ticket['ticket_number'] . ' · ' . ucfirst($ticket['status'])) ?></a></p><?php endforeach; ?>
<p class="notice">Approval releases seats and voids valid tickets. Payments are unchanged; no automatic refund is initiated.</p>
<?php if ($request['status'] === 'pending' && $request['booking_status'] === 'cancellation_requested'): ?>
<h2>Review request</h2>
<form class="account-form" method="post" data-confirm="Submit this cancellation review? Approval cancels the booking, releases seats, and voids tickets." action="/admin/cancellations/approve?id=<?= (int) $request['id'] ?>">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><label for="note">Admin note <span class="muted">(optional)</span></label><textarea id="note" name="note" maxlength="1000" rows="3" aria-describedby="note-help"><?= $escape($data['note']) ?></textarea><small id="note-help">Up to 1000 characters. Visible to the customer.</small>
    <div class="actions"><button class="button-danger" type="submit">Approve cancellation</button><button class="button-secondary" type="submit" formaction="/admin/cancellations/reject?id=<?= (int) $request['id'] ?>">Reject request</button></div>
</form>
<?php else: ?><p class="muted">This request cannot be reviewed in its current state.</p><?php endif; ?>
<p><a href="/admin/cancellations">Return to cancellations</a></p>
