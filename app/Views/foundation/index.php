<section class="hero" aria-labelledby="welcome-title">
    <div>
        <span class="eyebrow">Welcome to SkyReserve</span>
        <h1 id="welcome-title">Your next journey.<br><span>A simpler start.</span></h1>
        <p>Find your flight, plan your trip, and choose your seat. Bring your next destination a little closer.</p>
        <div class="actions">
            <a class="button" href="/flights">Search flights <span aria-hidden="true">↗</span></a>
            <?php if (!$data['user']): ?>
                <a class="button button-secondary" href="/register">Create account</a>
            <?php else: ?>
                <a class="button button-secondary" href="<?= $data['user']['role'] === 'admin' ? '/admin' : '/profile' ?>">Open your account</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="orbit"></div>
        <svg viewBox="0 0 100 100" fill="currentColor"><path d="M87 9c-3-3-8-2-11 1L58 29 20 19l-8 8 31 18-17 19-13-2-6 6 18 9 9 18 6-6-2-13 19-17 18 31 8-8-10-38 19-18c3-3 4-8 1-11Z"/></svg>
        <span class="journey-note">A window to your next destination</span>
    </div>
</section>
<section aria-labelledby="journey-title">
    <div class="section-heading"><h2 id="journey-title">From plans to takeoff</h2><a href="/flights">Explore available flights →</a></div>
    <div class="feature-grid">
        <article class="feature-card"><span class="feature-number">01</span><h3>Find your route</h3><p>Choose your departure, destination, and travel date. Compare schedules and fares in one place.</p></article>
        <article class="feature-card"><span class="feature-number">02</span><h3>Make it your journey</h3><p>Sign in to create a booking and add your passenger details.</p></article>
        <article class="feature-card"><span class="feature-number">03</span><h3>Choose your seat</h3><p>See your aircraft's available seats and select your preferred spot for the journey.</p></article>
    </div>
</section>
<section class="home-section" aria-labelledby="upcoming-title">
    <div class="section-heading"><h2 id="upcoming-title">Upcoming flights</h2><a class="button button-secondary" href="/flights">Search all flights</a></div>
    <p class="muted">Explore the next available departures. Times are shown in UTC; seat availability may change.</p>
    <?php if (!$data['upcomingFlights']): ?>
        <div class="empty-state" data-upcoming-empty><h3>Your next destination is on the horizon</h3><p>No scheduled flights are available right now. Check back for upcoming departures.</p><a class="button button-secondary" href="/flights">Search flights</a></div>
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
<section class="home-section" aria-labelledby="benefits-title">
    <div class="section-heading"><h2 id="benefits-title">Why choose SkyReserve</h2></div>
    <div class="benefit-grid">
        <?php foreach ([['Easy flight search', 'Compare routes, departure times, and fares in one place.', 'm4 17 5-5 4 3 7-10M15 5h5v5'], ['Simple seat selection', 'See available seats and choose a spot on your aircraft.', 'M6 3v10h12V3M4 13v5h16v-5M6 18v3M18 18v3'], ['Manual payment verification', 'Submit your payment details for review by the airline team.', 'M5 3h14v18H5zM8 8h8M8 12h4m0 4 2 2 4-4'], ['Easy booking and ticket management', 'Keep your booking details and printable tickets together.', 'M3 6h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4zM15 6v12']] as [$title, $text, $path]): ?>
            <article class="card benefit-card"><span class="benefit-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $escape($path) ?>"/></svg></span><h3><?= $escape($title) ?></h3><p><?= $escape($text) ?></p></article>
        <?php endforeach; ?>
    </div>
</section>
<section class="home-section" aria-labelledby="booking-journey-title">
    <div class="section-heading"><h2 id="booking-journey-title">Your booking journey</h2></div>
    <p class="muted">A clear path from finding a flight to receiving your ticket.</p>
    <ol class="booking-journey" aria-label="Booking steps">
        <?php foreach (['Search', 'Book', 'Choose Seat', 'Submit Payment', 'Verification', 'Ticket'] as $index => $step): ?>
            <li><span class="feature-number" aria-hidden="true"><?= sprintf('%02d', $index + 1) ?></span><span><?= $escape($step) ?></span><?php if ($index < 5): ?><span class="journey-arrow" aria-hidden="true">→</span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
    <p class="muted journey-explanation">The airline reviews your payment before confirmation. Your ticket becomes available after verification and a valid seat assignment.</p>
</section>
<section class="card home-final-cta" aria-labelledby="final-cta-title">
    <div><h2 id="final-cta-title">Ready for your next journey?</h2><p>Search available flights and reserve your seat in just a few steps.</p></div>
    <div class="actions"><a class="button" href="/flights">Search Flights</a>
        <?php if (($data['user']['role'] ?? '') === 'customer'): ?><a class="button button-secondary" href="/bookings">My Bookings</a>
        <?php elseif (($data['user']['role'] ?? '') === 'admin'): ?><a class="button button-secondary" href="/admin">Admin dashboard</a>
        <?php else: ?><a class="button button-secondary" href="/login">Login</a><?php endif; ?>
    </div>
</section>
