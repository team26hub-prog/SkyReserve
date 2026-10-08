<span class="eyebrow">Operations overview</span>
<h1>Admin area</h1>
<p class="muted">Welcome, <?= $escape($data['user']['name']) ?>. Your airline workspace is ready.</p>
<section aria-label="Dashboard metrics" class="dashboard-metrics">
    <?php foreach (['customers' => 'Total customers','flights' => 'Total flights','upcoming_flights' => 'Upcoming flights','bookings' => 'Total bookings','confirmed_bookings' => 'Confirmed bookings','pending_payments' => 'Pending payments','pending_cancellations' => 'Pending cancellation requests'] as $key => $label): ?>
        <article class="metric-card"><h2><?= $label ?></h2><strong data-metric="<?= $key ?>"><?= (int) $data['metrics'][$key] ?></strong></article>
    <?php endforeach; ?>
    <article class="metric-card metric-revenue"><h2>Total verified revenue</h2>
        <?php foreach ($data['metrics']['revenue'] as $revenue): ?><strong data-revenue-currency="<?= $escape($revenue['currency']) ?>"><?= $escape($revenue['currency'] . ' ' . $revenue['amount']) ?></strong><?php endforeach; ?>
        <?php if (!$data['metrics']['revenue']): ?><strong>0.00</strong><small>No verified payments</small><?php endif; ?>
    </article>
</section>
<p class="muted">Counts include all stored records. Upcoming flights are Scheduled/Delayed with a future UTC departure. Verified revenue is gross verified payment amounts, grouped by currency; manual cancellations do not automatically reduce it.</p>
<p><a class="button" href="/admin/reports">Open reports</a></p>
<div class="management-links">
    <a href="/admin/airports"><span class="feature-number" aria-hidden="true">⌖</span><br>Manage airports<small>Keep your destinations and airport information up to date.</small></a>
    <a href="/admin/aircraft"><span class="feature-number" aria-hidden="true">↗</span><br>Manage aircraft and seats<small>Organize your fleet, capacity, and seat configurations.</small></a>
    <a href="/admin/flights"><span class="feature-number" aria-hidden="true">⇄</span><br>Manage flights<small>Manage routes, schedules, fares, and flight statuses.</small></a>
    <a href="/admin/payments"><span class="feature-number"><?= (int) $data['pendingPayments'] ?></span><br>Review payments<small><?= (int) $data['pendingPayments'] ?> pending payment(s) awaiting review.</small></a>
    <a href="/admin/cancellations"><span class="feature-number"><?= (int) $data['pendingCancellations'] ?></span><br>Review cancellations<small><?= (int) $data['pendingCancellations'] ?> pending cancellation request(s).</small></a>
    <a href="/admin/reports"><span class="feature-number" aria-hidden="true">▤</span><br>View reports<small>Bookings, flights, payments, cancellations, and flight passenger lists.</small></a>
</div>
<h2>Administrator account</h2>
<dl class="profile-details">
    <dt>Email</dt><dd><?= $escape($data['user']['email']) ?></dd>
    <dt>Account type</dt><dd><span class="badge">Admin</span></dd>
</dl>
