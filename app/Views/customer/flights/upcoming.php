<section class="home-section" aria-labelledby="upcoming-title">
    <div class="section-heading"><h2 id="upcoming-title">Upcoming flights</h2></div>
    <p class="muted">Explore the next available departures. Times are shown in UTC; seat availability may change.</p>
    <?php if (!$data['upcomingFlights']): ?>
        <div class="empty-state" data-upcoming-empty><h3>Your next destination is on the horizon</h3><p>No scheduled flights are available right now. Check back for upcoming departures.</p></div>
    <?php else: ?>
        <div class="upcoming-grid">
        <?php foreach ($data['upcomingFlights'] as $flight): ?>
            <article class="card upcoming-card" data-upcoming-flight="<?= (int) $flight['id'] ?>" aria-labelledby="upcoming-flight-<?= (int) $flight['id'] ?>">
                <div class="page-heading"><h3 id="upcoming-flight-<?= (int) $flight['id'] ?>"><?= $escape($flight['flight_number']) ?></h3><span class="badge" data-status="scheduled">Scheduled</span></div>
                <div class="route-display" aria-label="<?= $escape($flight['origin_code'] . ' to ' . $flight['destination_code']) ?>"><span class="route-code"><?= $escape($flight['origin_code']) ?></span><span class="route-line" aria-hidden="true">→</span><span class="route-code"><?= $escape($flight['destination_code']) ?></span></div>
                <p class="muted"><?= $escape($flight['origin_city'] . ' → ' . $flight['destination_city']) ?></p>
                <dl class="upcoming-facts"><dt>Departure (UTC)</dt><dd><time datetime="<?= $escape(str_replace(' ', 'T', $flight['departure_at']) . 'Z') ?>"><?= $escape(gmdate('d M Y · H:i', strtotime($flight['departure_at'] . ' UTC'))) ?></time></dd><dt>Available seats</dt><dd><?= (int) $flight['available_seats'] ?></dd></dl>
                <div class="upcoming-price"><small>Fare per passenger</small><strong><?= $escape($flight['currency'] . ' ' . number_format((float) $flight['base_fare'], 2)) ?></strong></div>
                <a class="button button-secondary" href="/flights/show?id=<?= (int) $flight['id'] ?>" aria-label="View flight <?= $escape($flight['flight_number']) ?>">View flight <span aria-hidden="true">→</span></a>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
