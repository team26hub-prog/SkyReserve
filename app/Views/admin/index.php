<span class="eyebrow">Operations overview</span>
<h1>Admin panel</h1>
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
    <?php
    $managementCards = [
        '/admin/airports' => ['Manage airports', 'Keep your destinations and airport information up to date.'],
        '/admin/aircraft' => ['Manage aircraft and seats', 'Organize your fleet, capacity, and seat configurations.'],
        '/admin/flights' => ['Manage flights', 'Manage routes, schedules, fares, and flight statuses.'],
        '/admin/payments' => ['Review payments', (int) $data['pendingPayments'] . ' pending payment(s) awaiting review.'],
        '/admin/cancellations' => ['Review cancellations', (int) $data['pendingCancellations'] . ' pending cancellation request(s).'],
        '/admin/reports' => ['View reports', 'Bookings, flights, payments, cancellations, and flight passenger lists.'],
    ];
    // Use the same SVG symbols as the admin navigation rendered by the layout.
    foreach ($managementCards as $path => [$label, $description]): ?>
    <a href="<?= $path ?>"><span class="management-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><?= $adminLinks[$path][1] ?></svg></span><br><?= $escape($label) ?><small><?= $escape($description) ?></small></a>
    <?php endforeach; ?>
</div>
<h2>Administrator account</h2>
<dl class="profile-details">
    <dt>Email</dt><dd><?= $escape($data['user']['email']) ?></dd>
    <dt>Account type</dt><dd><span class="badge">Admin</span></dd>
</dl>
