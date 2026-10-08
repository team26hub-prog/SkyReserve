<?php use App\Models\AdminReport; ?>
<span class="eyebrow">Operations · Reporting</span><h1>Admin reports</h1>
<p class="muted">Explore current records with UTC date, flight, and status filters. Reports are read-only.</p>
<div class="management-links">
<?php foreach (AdminReport::REPORTS as $type => $report): ?><a href="/admin/reports/<?= $type ?>"><?= $escape($report['title']) ?><small><?= $escape($report['date']) ?> dates and relevant status filters.</small></a><?php endforeach; ?>
</div><p><a class="button button-secondary" href="/admin">Return to dashboard</a></p>
