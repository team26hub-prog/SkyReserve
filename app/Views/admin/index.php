<span class="eyebrow">Operations overview</span>
<h1>Admin panel</h1>
<p class="muted">Welcome, <?= $escape($data['user']['name']) ?>. Your airline workspace is ready.</p>
<?php
$metricCards = [
    'customers' => ['Total customers', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/><circle cx="9" cy="7" r="4"/>'],
    'flights' => ['Total flights', '<path d="M21 3a2.1 2.1 0 0 1 0 3l-5 5 2 8-2 2-4-7-5 5v3l-2-2-2-2h3l5-5-7-4 2-2 8 2 5-5Z"/>'],
    'upcoming_flights' => ['Upcoming flights', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/><circle cx="15" cy="16" r="3"/><path d="M15 14.5V16l1 1"/>'],
    'bookings' => ['Total bookings', '<path d="M3 6h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4V6Z"/><path d="M15 6v2m0 3v2m0 3v2"/>'],
    'confirmed_bookings' => ['Confirmed bookings', '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'],
    'pending_payments' => ['Pending payments', '<path d="M20 8V5a2 2 0 0 0-2-2H6a3 3 0 0 0 0 6h14v12H6a3 3 0 0 1-3-3V6"/><rect x="14" y="12" width="7" height="5" rx="1"/><path d="M17 14.5h.01"/>'],
    'pending_cancellations' => ['Pending cancellation requests', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M9 14l6 4m0-4-6 4"/>'],
];
?>
<section aria-label="Dashboard metrics" class="dashboard-metrics">
    <?php foreach ($metricCards as $key => [$label, $icon]): ?>
        <article class="metric-card">
            <span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><?= $icon ?></svg></span>
            <div class="metric-content"><strong data-metric="<?= $key ?>"><?= (int) $data['metrics'][$key] ?></strong><h2><?= $escape($label) ?></h2></div>
        </article>
    <?php endforeach; ?>
    <article class="metric-card metric-revenue">
        <span class="metric-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M6 12h.01M18 12h.01M12 7v10m2-8h-3a1.5 1.5 0 0 0 0 3h2a1.5 1.5 0 0 1 0 3h-3"/></svg></span>
        <div class="metric-content">
        <?php foreach ($data['metrics']['revenue'] as $revenue): ?><strong data-revenue-currency="<?= $escape($revenue['currency']) ?>"><?= $escape($revenue['currency'] . ' ' . $revenue['amount']) ?></strong><?php endforeach; ?>
        <?php if (!$data['metrics']['revenue']): ?><strong>0.00</strong><?php endif; ?>
        <h2>Total verified revenue</h2>
        <?php if (!$data['metrics']['revenue']): ?><small>No verified payments</small><?php endif; ?>
        </div>
    </article>
</section>
<p class="muted">Counts include all stored records. Upcoming flights are Scheduled/Delayed with a future UTC departure. Verified revenue is gross verified payment amounts, grouped by currency; manual cancellations do not automatically reduce it.</p>
<p><a class="button" href="/admin/reports">Open reports</a></p>
<div class="management-links management-links-inline">
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
    <a href="<?= $path ?>"><span class="management-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><?= $adminLinks[$path][1] ?></svg></span><span class="management-content"><?= $escape($label) ?><small><?= $escape($description) ?></small></span></a>
    <?php endforeach; ?>
</div>
<h2>Administrator account</h2>
<dl class="profile-details">
    <dt>Email</dt><dd><?= $escape($data['user']['email']) ?></dd>
    <dt>Account type</dt><dd><span class="badge">Admin</span></dd>
</dl>
