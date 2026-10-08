<?php use App\Models\AdminReport; $type = $data['type']; $result = $data['result']; ?>
<span class="eyebrow">Operations · Reporting</span><h1><?= $escape($data['title']) ?></h1>
<nav class="filter-tabs" aria-label="Report navigation"><?php foreach (AdminReport::REPORTS as $key => $report): ?><a href="/admin/reports/<?= $key ?>"<?= $key === $type ? ' aria-current="page"' : '' ?>><?= $escape($report['title']) ?></a><?php endforeach; ?></nav>
<?php if ($data['error']): ?><p class="notice error" role="alert"><?= $escape($data['error']) ?></p><?php endif; ?>
<?php require BASE_PATH . '/app/Views/admin/reports/filters.php'; ?>
<?php if (!$data['error']): ?>
<div class="report-totals" aria-label="Filtered report totals"><p><strong data-report-total="<?= (int) $result['total'] ?>"><?= (int) $result['total'] ?></strong> matching <?= $type === 'passengers' ? 'passenger(s)' : 'record(s)' ?></p>
<?php foreach ($result['totals'] as $total): ?><p><span><?= $type === 'bookings' ? 'Booked value' : 'Submitted amount' ?>:</span> <strong><?= $escape($total['currency'] . ' ' . $total['amount']) ?></strong><?php if ($type === 'payments'): ?><small class="cell-note">Verified amount: <?= $escape($total['currency'] . ' ' . $total['verified_amount']) ?></small><?php endif; ?></p><?php endforeach; ?></div>
<?php if ($type === 'bookings'): ?><p class="muted">Booked value includes all matching booking statuses and is not verified revenue. Payment status reflects the latest submission; counts show all passengers, active seats, and valid tickets.</p><?php elseif ($type === 'payments'): ?><p class="muted">Includes each matching payment submission, including rejected history. Verified amounts are gross recorded payments; cancellation does not automatically deduct a refund. Currencies are totalled separately.</p><?php elseif ($type === 'flights'): ?><p class="muted">Seat counts describe active configured aircraft seats and reserved/confirmed allocations, rather than customer search eligibility.</p><?php endif; ?>
<?php if (!$result['rows']): ?><p class="empty-state"><?= $type === 'passengers' && $result['filters']['flight_id'] === '' ? 'Choose a flight to view its passenger list.' : 'No records match these filters. Adjust or clear the fields to see more records.' ?></p><?php else: ?>
<?php require BASE_PATH . '/app/Views/admin/reports/table.php'; ?>
<p class="muted">Showing <?= count($result['rows']) ?> of <?= (int) $result['total'] ?> matching records. Totals cover the full filtered result.</p>
<?php if ($result['pages'] > 1): ?><nav class="report-pagination" aria-label="Report pages">
    <?php if ($result['page'] > 1): ?><a class="button button-secondary" href="?<?= $escape(http_build_query(array_replace($result['filters'],['page' => $result['page']-1]))) ?>">Previous</a><?php endif; ?>
    <span>Page <?= (int) $result['page'] ?> of <?= (int) $result['pages'] ?></span>
    <?php if ($result['page'] < $result['pages']): ?><a class="button button-secondary" href="?<?= $escape(http_build_query(array_replace($result['filters'],['page' => $result['page']+1]))) ?>">Next</a><?php endif; ?>
</nav><?php endif; ?>
<?php endif; endif; ?>
<nav class="actions" aria-label="Report shortcuts"><a class="button button-secondary" href="/admin/reports">All reports</a><a class="button button-secondary" href="/admin">Dashboard</a></nav>
