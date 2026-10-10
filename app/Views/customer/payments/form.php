<?php $booking = $data['booking']; $values = $data['values']; $instructions = $data['instructions']; ?>
<span class="eyebrow">Your booking · Payment details</span>
<h1>Submit payment</h1>
<p class="muted">Send your payment details for manual review. Your booking will remain unconfirmed until verification.</p>
<dl class="profile-details">
    <dt>Booking reference / PNR</dt><dd><strong><?= $escape($booking['booking_reference']) ?></strong></dd>
    <dt>Amount due</dt><dd><?= $escape($booking['currency'] . ' ' . $booking['total_amount']) ?></dd>
</dl>
<section class="card booking-context" aria-labelledby="payment-instructions">
    <h2 id="payment-instructions">Airline payment instructions</h2>
    <p><?= nl2br($escape($instructions['instructions'])) ?></p>
    <dl class="payment-instructions">
        <dt>Bank</dt><dd><?= $escape($instructions['bank_name']) ?></dd>
        <dt>Account name</dt><dd><?= $escape($instructions['account_name']) ?></dd>
        <dt>Account / IBAN</dt><dd><?= $escape($instructions['account_number']) ?></dd>
    </dl>
</section>
<?php if ($data['errors']): ?><div class="notice error" role="alert"><ul><?php foreach ($data['errors'] as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="account-form" action="/bookings/payment?booking_id=<?= (int) $booking['id'] ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="method">Payment method</label>
    <select id="method" name="method" required><option value="">Choose payment method</option>
        <?php foreach ($data['methods'] as $value => $label): ?><option value="<?= $value ?>"<?= ($values['method'] ?? '') === $value ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?>
    </select>
    <label for="amount">Amount paid (<?= $escape($booking['currency']) ?>)</label>
    <input id="amount" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" value="<?= $escape($values['amount'] ?? $booking['total_amount']) ?>" required>
    <label for="transaction_reference">Transaction / reference number</label>
    <input id="transaction_reference" name="transaction_reference" maxlength="120" value="<?= $escape($values['transaction_reference'] ?? '') ?>" aria-describedby="reference-help" required>
    <small id="reference-help">For cash payments, enter the airline's cash receipt reference.</small>
    <label for="payment_date">Payment date (UTC)</label>
    <input id="payment_date" name="payment_date" type="date" min="1900-01-01" max="<?= $data['today'] ?>" value="<?= $escape($values['payment_date'] ?? $data['today']) ?>" required>
    <label for="receipt">Receipt image <span class="muted">(optional)</span></label>
    <input id="receipt" name="receipt" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="receipt-help">
    <small id="receipt-help">JPG, JPEG, PNG, or WEBP, up to 5 MB. Images must be no larger than 6000 pixels per side and 12 megapixels. After an error, select the receipt again.</small>
    <div class="actions form-actions">
        <button type="submit">Submit payment for review</button>
        <a class="button button-secondary" href="/bookings/show?id=<?= (int) $booking['id'] ?>">Return to booking summary</a>
    </div>
</form>
