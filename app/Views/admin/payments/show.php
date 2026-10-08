<?php use App\Models\Payment; $payment = $data['payment']; ?>
<span class="eyebrow">Operations · Manual verification</span>
<div class="page-heading"><h1>Payment details</h1><span class="badge" data-status="<?= $escape($payment['status']) ?>"><?= $escape(ucfirst($payment['status'])) ?></span></div>
<?php if ($data['error']): ?><div class="notice error" role="alert"><?= $escape($data['error']) ?></div><?php endif; ?>
<dl class="profile-details">
    <dt>Customer</dt><dd><?= $escape($payment['customer_name']) ?><small class="cell-note"><?= $escape($payment['customer_email']) ?></small></dd>
    <dt>Booking reference / PNR</dt><dd><strong><?= $escape($payment['booking_reference']) ?></strong></dd>
    <dt>Booking status</dt><dd><?= $payment['booking_status'] === 'payment_submitted' ? 'Payment Submitted / Awaiting Verification' : ($payment['booking_status'] === 'pending' ? 'Pending Payment' : $escape(ucfirst($payment['booking_status']))) ?></dd>
    <dt>Flight</dt><dd><?= $escape($payment['flight_number'] . ' — ' . $payment['origin_code'] . ' → ' . $payment['destination_code']) ?></dd>
    <dt>Departure (UTC)</dt><dd><?= $escape($payment['departure_at']) ?></dd>
    <dt>Arrival (UTC)</dt><dd><?= $escape($payment['arrival_at']) ?></dd>
    <dt>Amount due</dt><dd><?= $escape($payment['booking_currency'] . ' ' . $payment['amount_due']) ?></dd>
    <dt>Amount submitted</dt><dd><?= $escape($payment['currency'] . ' ' . $payment['amount']) ?></dd>
    <dt>Payment method</dt><dd><?= $escape(Payment::METHODS[$payment['method']] ?? ucfirst($payment['method'])) ?></dd>
    <dt>Transaction reference</dt><dd><?= $escape($payment['transaction_reference'] ?? 'Not provided') ?></dd>
    <dt>Payment date (UTC)</dt><dd><?= $escape($payment['payment_date'] ?? 'Not provided') ?></dd>
    <dt>Receipt</dt><dd><?php if ($payment['proof_path']): ?><a href="/admin/payments/receipt?id=<?= (int) $payment['id'] ?>">View protected receipt</a><?php else: ?>No receipt uploaded<?php endif; ?></dd>
    <dt>Reviewed by</dt><dd><?= $escape($payment['reviewer_name'] ?? 'Not reviewed') ?></dd>
    <dt>Reviewed at (UTC)</dt><dd><?= $escape($payment['reviewed_at'] ?? 'Not reviewed') ?></dd>
    <?php if ($payment['review_notes'] !== null): ?><dt>Rejection reason</dt><dd><?= nl2br($escape($payment['review_notes'])) ?></dd><?php endif; ?>
</dl>
<h2>Passenger details</h2>
<?php foreach ($data['passengers'] as $passenger): ?>
    <dl class="profile-details"><dt>Full name</dt><dd><?= $escape($passenger['full_name'] ?? trim($passenger['first_name'] . ' ' . $passenger['last_name'])) ?></dd><dt>CNIC / Passport</dt><dd><?= $escape($passenger['document_number'] ?? 'Not provided') ?></dd></dl>
<?php endforeach; ?>
<?php if (!$data['passengers']): ?><p class="empty-state">No passenger details are recorded for this booking.</p><?php endif; ?>
<?php if ($payment['status'] === 'pending' && $payment['booking_status'] === 'payment_submitted'): ?>
    <h2>Review payment</h2>
    <p class="muted">Compare the submitted amount and receipt with the amount due. Verify confirms the booking; reject allows the customer to submit payment again when the booking is still eligible.</p>
    <form action="/admin/payments/verify?id=<?= (int) $payment['id'] ?>" method="post" data-confirm="Verify this payment and confirm the booking?">
        <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button type="submit">Verify payment</button>
    </form>
    <form class="account-form review-form" action="/admin/payments/reject?id=<?= (int) $payment['id'] ?>" method="post" data-confirm="Reject this payment and return the booking to payment required?">
        <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
        <label for="reason">Rejection reason <span class="muted">(optional)</span></label>
        <textarea id="reason" name="reason" maxlength="1000" rows="3" aria-describedby="reason-help"><?= $escape($data['reason']) ?></textarea>
        <small id="reason-help">Up to 1000 characters. The customer can see this explanation.</small>
        <button class="button-danger" type="submit">Reject payment</button>
    </form>
<?php else: ?>
    <p class="notice success" role="status">This payment cannot be reviewed in its current payment/booking state.</p>
<?php endif; ?>
<p><a href="/admin/payments?status=<?= isset(Payment::REVIEW_STATUSES[$payment['status']]) ? $escape($payment['status']) : 'pending' ?>">Return to payments</a></p>
