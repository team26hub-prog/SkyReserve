<?php
use App\Models\AdminReport;
$analytics = $data['analytics'];
$period = $analytics['period'] ?? '30';
?>
<section class="analytics-overview" aria-labelledby="analytics-title">
    <div class="analytics-heading">
        <div><span class="eyebrow">Insights at a glance</span><h2 id="analytics-title">Analytics Overview</h2><p class="muted">Read-only insights from your airline records. All dates use UTC.</p></div>
        <form class="analytics-period" action="/admin/reports" method="get" data-auto-filter>
            <label for="analytics-period">Trend period</label>
            <select id="analytics-period" name="period"><?php foreach (AdminReport::ANALYTICS_PERIODS as $value => $label): ?><option value="<?= $escape((string) $value) ?>"<?= (string) $value === $period ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select>
            <noscript><button type="submit">Update charts</button></noscript>
        </form>
    </div>
    <?php if ($data['analyticsError']): ?>
        <div class="notice error" role="alert"><?= $escape($data['analyticsError']) ?></div>
    <?php elseif ($analytics): ?>
    <div class="analytics-grid">
        <article class="card analytics-card" aria-labelledby="status-chart-title">
            <h3 id="status-chart-title">Bookings by Status</h3><p class="muted chart-note">All stored bookings, by current status.</p>
            <div class="analytics-card-body">
            <?php if (array_sum(array_column($analytics['statuses'], 'count'))): ?>
                <div class="analytics-chart chart-doughnut" data-chart="statuses" aria-hidden="true"></div>
                <ul class="chart-legend"><?php foreach ($analytics['statuses'] as $index => $row): ?><li><span class="chart-swatch chart-color-<?= $index ?>" aria-hidden="true"></span><span><?= $escape($row['label']) ?></span><strong><?= (int) $row['count'] ?></strong></li><?php endforeach; ?></ul>
            <?php else: ?><div class="chart-empty"><strong>No bookings yet</strong><p>Status insights will appear when the first booking is created.</p></div><?php endif; ?>
            </div>
        </article>
        <article class="card analytics-card" aria-labelledby="booking-chart-title">
            <h3 id="booking-chart-title">Booking Trend</h3><p class="muted chart-note">Bookings created, <?= $escape($analytics['start']) ?> – <?= $escape($analytics['end']) ?>.</p>
            <div class="analytics-card-body">
            <?php if (array_sum(array_column($analytics['bookings'], 'count'))): ?>
                <div class="analytics-chart" data-chart="bookings" aria-hidden="true"></div>
                <details class="chart-data"><summary>View booking data</summary><div class="chart-table-wrap"><table><thead><tr><th scope="col">Date (UTC)</th><th scope="col">Bookings</th></tr></thead><tbody><?php foreach ($analytics['bookings'] as $row): ?><tr><td><?= $escape($row['bucket']) ?></td><td><?= (int) $row['count'] ?></td></tr><?php endforeach; ?></tbody></table></div></details>
            <?php else: ?><div class="chart-empty"><strong>No bookings in this period</strong><p>Choose another period to explore booking activity.</p></div><?php endif; ?>
            </div>
        </article>
        <article class="card analytics-card" aria-labelledby="revenue-chart-title">
            <h3 id="revenue-chart-title">Verified Revenue Trend</h3><p class="muted chart-note">Verified payments by submission date (UTC). Gross amounts include cancelled bookings; each currency has its own chart.</p>
            <div class="analytics-card-body">
            <?php if ($analytics['revenue']): foreach ($analytics['revenue'] as $index => $series): ?>
                <div class="revenue-series"><h4><?= $escape($series['currency']) ?></h4><div class="analytics-chart" data-chart="revenue" data-series="<?= $index ?>" aria-hidden="true"></div>
                <details class="chart-data"><summary>View <?= $escape($series['currency']) ?> revenue data</summary><div class="chart-table-wrap"><table><thead><tr><th scope="col">Date (UTC)</th><th scope="col">Amount (<?= $escape($series['currency']) ?>)</th></tr></thead><tbody><?php foreach ($series['points'] as $row): ?><tr><td><?= $escape($row['bucket']) ?></td><td><?= $escape($row['amount']) ?></td></tr><?php endforeach; ?></tbody></table></div></details></div>
            <?php endforeach; else: ?><div class="chart-empty"><strong>No verified revenue in this period</strong><p>Only verified payments contribute to this chart. Choose another period to explore earlier activity.</p></div><?php endif; ?>
            </div>
        </article>
        <article class="card analytics-card" aria-labelledby="routes-chart-title">
            <h3 id="routes-chart-title">Top Routes</h3><p class="muted chart-note">Top 5 directional routes by booking count, across all stored bookings.</p>
            <div class="analytics-card-body">
            <?php if ($analytics['routes']): ?>
                <div class="analytics-chart" data-chart="routes" aria-hidden="true"></div>
                <details class="chart-data"><summary>View route data</summary><div class="chart-table-wrap"><table><thead><tr><th scope="col">Route</th><th scope="col">Bookings</th></tr></thead><tbody><?php foreach ($analytics['routes'] as $row): ?><tr><td><?= $escape($row['origin'] . ' → ' . $row['destination']) ?></td><td><?= (int) $row['count'] ?></td></tr><?php endforeach; ?></tbody></table></div></details>
            <?php else: ?><div class="chart-empty"><strong>No route activity yet</strong><p>Your most booked routes will appear here as bookings are created.</p></div><?php endif; ?>
            </div>
        </article>
    </div>
    <noscript><p class="muted">Chart data is available in the legends and expandable tables. Enable JavaScript to display the charts.</p></noscript>
    <script type="application/json" id="analytics-data"><?= json_encode($analytics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
    <script src="/assets/js/report-analytics.js" defer></script>
    <?php endif; ?>
</section>
