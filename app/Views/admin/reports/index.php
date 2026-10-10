<?php use App\Models\AdminReport; ?>
<span class="eyebrow">Operations · Reporting</span><h1>Admin reports</h1>
<p class="muted">Explore current records with UTC date, flight, and status filters. Reports are read-only.</p>
<?php
$reportVisuals = [
    'bookings' => ['Review reservations, passenger counts, seats, and booking statuses.', '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 10h6m-6 4h6m-6 4h3"/>'],
    'flights' => ['Explore schedules, routes, aircraft, and seat allocations.', '<path d="M21 3a2.1 2.1 0 0 1 0 3l-5 5 2 8-2 2-4-7-5 5v3l-2-2-2-2h3l5-5-7-4 2-2 8 2 5-5Z"/>'],
    'payments' => ['Review payment submissions, verification, and transaction history.', '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h3m4 0h3"/>'],
    'cancellations' => ['Track cancellation requests, decisions, and review notes.', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-12 3 6 4m0-4-6 4"/>'],
    'passengers' => ['View the passenger manifest, seat assignments, and tickets by flight.', '<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 4a3 3 0 0 1 0 6m2 4a5 5 0 0 1 3 4v3"/>'],
    'revenue' => ['Explore verified payment records and revenue totals by currency.', '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M12 8v8m3-7h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9M6 11v2m12-2v2"/>'],
];
?>
<div class="report-card-grid">
<?php foreach (AdminReport::REPORTS as $type => $report): [$description, $icon] = $reportVisuals[$type]; ?>
    <a class="card report-card" href="/admin/reports/<?= $escape($type) ?>">
        <div class="report-card-heading"><span class="report-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" focusable="false"><?= $icon ?></svg></span><h2><?= $escape($report['title']) ?></h2></div>
        <p><?= $escape($description) ?></p>
        <span class="report-card-cta">Open report <span aria-hidden="true">→</span></span>
    </a>
<?php endforeach; ?>
</div>
<?php require BASE_PATH . '/app/Views/admin/reports/analytics.php'; ?>
<p><a class="button button-secondary" href="/admin">Return to dashboard</a></p>
