<span class="eyebrow">Operations · Payment review</span>
<div class="page-heading"><h1>Payments</h1><span class="badge"><?= count($data['payments']) ?> <?= $escape(strtolower($data['status'])) ?> payment(s)</span></div>
<p class="muted">Review customer payment details and receipts before confirming a booking.</p>
<?php if ($data['error']): ?><div class="notice error" role="alert"><?= $escape($data['error']) ?></div><?php endif; ?>
<nav class="filter-tabs" aria-label="Payment status filters">
    <?php foreach ($data['statuses'] as $value => $label): ?><a href="/admin/payments?status=<?= $value ?>"<?= $data['status'] === $value ? ' aria-current="page"' : '' ?>><?= $escape($label) ?></a><?php endforeach; ?>
</nav>
<?php if (!$data['payments']): ?>
    <p class="empty-state">No <?= $escape($data['status']) ?> payments to show.</p>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Payment list">
        <table>
            <caption class="sr-only">Submitted payments, customer bookings, and review actions</caption>
            <thead><tr><th scope="col">Booking / PNR</th><th scope="col">Customer</th><th scope="col">Flight</th><th scope="col">Amount submitted</th><th scope="col">Payment date (UTC)</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody><?php foreach ($data['payments'] as $payment): ?><tr>
                <td><strong><?= $escape($payment['booking_reference']) ?></strong></td>
                <td><?= $escape($payment['customer_name']) ?><small class="cell-note"><?= $escape($payment['customer_email']) ?></small></td>
                <td><?= $escape($payment['flight_number']) ?><small class="cell-note"><?= $escape($payment['origin_code'] . ' → ' . $payment['destination_code']) ?></small></td>
                <td class="fare"><?= $escape($payment['currency'] . ' ' . $payment['amount']) ?></td>
                <td><?= $escape($payment['payment_date'] ?? 'Not provided') ?></td>
                <td><span class="badge" data-status="<?= $escape($payment['status']) ?>"><?= $escape(ucfirst($payment['status'])) ?></span></td>
                <td><a href="/admin/payments/show?id=<?= (int) $payment['id'] ?>" aria-label="View payment for <?= $escape($payment['booking_reference']) ?>">View payment</a></td>
            </tr><?php endforeach; ?></tbody>
        </table>
    </div>
<?php endif; ?>
