<?php if ($data['cancellations']): ?>
<section aria-label="Cancellation history"><h2>Cancellation requests</h2>
<?php foreach ($data['cancellations'] as $request): ?><dl class="profile-details">
    <dt>Request status</dt><dd><span class="badge" data-status="<?= $escape($request['status']) ?>"><?= $escape(ucfirst($request['status'])) ?></span></dd>
    <dt>Requested at (UTC)</dt><dd><?= $escape($request['created_at']) ?></dd>
    <dt>Reason</dt><dd><?= nl2br($escape($request['reason'] ?? 'No reason provided')) ?></dd>
    <dt>Admin note</dt><dd><?= nl2br($escape($request['review_notes'] ?? 'No note provided')) ?></dd>
    <dt>Reviewed at (UTC)</dt><dd><?= $escape($request['reviewed_at'] ?? 'Awaiting review') ?></dd>
</dl><?php endforeach; ?>
<p class="muted">Payments remain manual. Cancellation does not initiate an automatic refund.</p></section>
<?php endif; ?>
