<?php $paymentDetails = $data['paymentDetails']; ?>
<section class="card manual-payment-details" aria-labelledby="manual-payment-heading">
    <h2 id="manual-payment-heading">Payment Details</h2>
    <p class="muted">Use these details when making a manual payment for your booking.</p>
    <dl class="manual-payment-grid">
        <div>
            <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-6 9 6H3Zm2 3v6m5-6v6m4-6v6m5-6v6M3 21h18"/></svg>Bank name</dt>
            <dd><?= $escape($paymentDetails['bank_name']) ?></dd>
        </div>
        <div>
            <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>Account name</dt>
            <dd><?= $escape($paymentDetails['account_name']) ?></dd>
        </div>
        <div>
            <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></svg>Account / IBAN</dt>
            <dd class="manual-payment-account"><span id="manual-payment-account"><?= $escape($paymentDetails['account_number']) ?></span><button type="button" class="button-secondary payment-copy" data-copy-payment-account hidden aria-label="Copy account or IBAN">Copy</button></dd>
        </div>
        <div>
            <dt><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h16v14H3zM3 7V4h14v3m0 6h4v4h-4z"/></svg>Supported payment methods</dt>
            <dd><?= $escape(implode(' · ', $data['paymentMethods'])) ?></dd>
        </div>
    </dl>
    <div class="manual-payment-guidance">
        <p><?= nl2br($escape($paymentDetails['instructions'])) ?></p>
        <p><strong>Payment reference:</strong> Use your booking reference / PNR as the payment reference so your payment can be matched to your booking.</p>
    </div>
    <p class="payment-copy-status sr-only" role="status" aria-live="polite"></p>
</section>
<script src="/assets/js/payment-details.js" defer></script>
