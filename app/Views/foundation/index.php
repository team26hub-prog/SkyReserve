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
