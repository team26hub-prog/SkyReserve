<?php require BASE_PATH . '/app/Views/foundation/graphics.php'; ?>
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
    <div class="hero-art" aria-hidden="true"></div>
</section>
<section aria-labelledby="journey-title">
    <div class="section-heading"><h2 id="journey-title">From plans to takeoff</h2><a href="/flights">Explore available flights →</a></div>
    <div class="feature-grid">
        <article class="feature-card"><div class="feature-heading"><span class="home-icon-container"><?= $homeIcon('route') ?></span><h3>Find your route</h3><span class="feature-number" aria-hidden="true">01</span></div><p>Choose your departure, destination, and travel date. Compare schedules and fares in one place.</p></article>
        <article class="feature-card"><div class="feature-heading"><span class="home-icon-container"><?= $homeIcon('passenger') ?></span><h3>Make it your journey</h3><span class="feature-number" aria-hidden="true">02</span></div><p>Sign in to create a booking and add your passenger details.</p></article>
        <article class="feature-card"><div class="feature-heading"><span class="home-icon-container"><?= $homeIcon('seat') ?></span><h3>Choose your seat</h3><span class="feature-number" aria-hidden="true">03</span></div><p>See your aircraft's available seats and select your preferred spot for the journey.</p></article>
    </div>
</section>
<section class="home-section" aria-labelledby="benefits-title">
    <div class="section-heading"><h2 id="benefits-title">Why choose SkyReserve</h2></div>
    <div class="benefit-grid">
        <?php foreach ([['Easy flight search', 'Compare routes, departure times, and fares in one place.', 'search'], ['Simple seat selection', 'See available seats and choose a spot on your aircraft.', 'seat'], ['Manual payment verification', 'Submit your payment details for review by the airline team.', 'verify'], ['Easy booking and ticket management', 'Keep your booking details and printable tickets together.', 'ticket']] as [$title, $text, $icon]): ?>
            <article class="card benefit-card"><span class="benefit-icon" aria-hidden="true"><?= $homeIcon($icon) ?></span><div class="benefit-content"><h3><?= $escape($title) ?></h3><p><?= $escape($text) ?></p></div></article>
        <?php endforeach; ?>
    </div>
</section>
<div class="home-flight-divider" aria-hidden="true"><svg viewBox="0 0 800 56" fill="none" focusable="false"><path d="M10 40C180 2 300 60 430 27S650 5 790 28" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 7"/><circle cx="10" cy="40" r="4" fill="currentColor"/><circle cx="790" cy="28" r="4" fill="currentColor"/><use href="#home-plane" x="395" y="12" width="28" height="28" stroke="currentColor" stroke-width="1.5"/></svg></div>
<section class="home-section" aria-labelledby="booking-journey-title">
    <div class="section-heading"><h2 id="booking-journey-title">Your booking journey</h2></div>
    <p class="muted">A clear path from finding a flight to receiving your ticket.</p>
    <ol class="booking-journey" aria-label="Booking steps">
        <?php
        $journeyRole = $data['user']['role'] ?? null;
        $journeyPaths = match ($journeyRole) {
            'customer' => ['/flights', '/bookings', '/bookings?section=seats', '/bookings?section=payments', '/bookings?section=payments&status=payment_submitted', '/bookings?section=tickets'],
            'admin' => ['/flights', '/admin/flights', '/admin/aircraft', '/admin/payments', '/admin/payments?status=pending', '/admin/payments?status=verified'],
            default => ['/flights', '/login', '/login', '/login', '/login', '/login'],
        };
        $journeyIcons = ['journey-search', 'journey-book', 'journey-seat', 'journey-payment', 'journey-verify', 'journey-ticket'];
        foreach (['Search', 'Book', 'Choose Seat', 'Payment', 'Verification', 'Ticket'] as $index => $step): ?>
            <li><a href="<?= $escape($journeyPaths[$index]) ?>"><span class="sr-only">Step <?= $index + 1 ?>: </span><span class="feature-number journey-icon" aria-hidden="true"><?= $homeIcon($journeyIcons[$index]) ?></span><span><?= $escape($step) ?></span></a><?php if ($index < 5): ?><span class="journey-arrow" aria-hidden="true"><?= $homeIcon('plane') ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
    <p class="muted journey-explanation">The airline reviews your payment before confirmation. Your ticket becomes available after verification and a valid seat assignment.</p>
</section>
<section class="home-section" aria-labelledby="destinations-title">
    <div class="section-heading"><h2 id="destinations-title">Find your next destination</h2><a href="/flights">Explore routes →</a></div>
    <p class="muted">A little inspiration for your next journey. Check Search Flights for available routes and travel dates.</p>
    <div class="destination-grid">
        <?php
        // Destination inspiration only; these cards do not imply flight availability.
        foreach ([['Lahore', 'LHE', 'Allama Iqbal International Airport', 'Pakistan', 'lahore', 'Badshahi Mosque in Lahore'], ['Karachi', 'KHI', 'Jinnah International Airport', 'Pakistan', 'karachi', 'Mazar-e-Quaid in Karachi'], ['Islamabad', 'ISB', 'Islamabad International Airport', 'Pakistan', 'islamabad', 'Panoramic view of Faisal Mosque in Islamabad'], ['Dubai', 'DXB', 'Dubai International Airport', 'United Arab Emirates', 'dubai', 'Dubai skyline'], ['Jeddah', 'JED', 'King Abdulaziz International Airport', 'Saudi Arabia', 'jeddah', 'Jeddah waterfront on the Red Sea'], ['Istanbul', 'IST', 'Istanbul Airport', 'Türkiye', 'istanbul', 'Istanbul skyline across the Bosphorus']] as [$city, $code, $airport, $country, $image, $alt]): ?>
            <a class="card destination-card" href="/flights" aria-label="<?= $escape('Explore ' . $city . ' — open Search Flights') ?>">
                <div class="destination-art"><img src="/assets/images/destinations/<?= $escape($image) ?>.png" alt="<?= $escape($alt) ?>" width="960" height="540" loading="lazy" decoding="async"><span class="destination-code"><span class="sr-only">Airport code: </span><?= $escape($code) ?></span></div>
                <div class="destination-content"><span class="destination-country"><?= $escape($country) ?></span><h3><?= $escape($city) ?></h3><p><?= $escape($airport) ?></p><span class="destination-link">Explore flights <span aria-hidden="true">→</span></span></div>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="destination-credits"><a href="/assets/images/destinations/credits.html">Destination photo credits</a></p>
</section>
<section class="card home-final-cta" aria-labelledby="final-cta-title">
    <svg class="home-cta-art" viewBox="0 0 420 180" fill="none" aria-hidden="true" focusable="false"><circle cx="330" cy="100" r="95"/><ellipse cx="330" cy="100" rx="48" ry="95"/><path d="M235 100h190M250 55h160M250 145h160M5 155C100 20 180 190 340 30" stroke-dasharray="4 7"/><use href="#home-plane" x="175" y="65" width="44" height="44"/></svg>
    <div><h2 id="final-cta-title">Ready for your next journey?</h2><p>Search available flights and reserve your seat in just a few steps.</p></div>
    <div class="actions"><a class="button" href="/flights">Search Flights</a>
        <?php if (($data['user']['role'] ?? '') === 'customer'): ?><a class="button button-secondary" href="/bookings">My Bookings</a>
        <?php elseif (($data['user']['role'] ?? '') === 'admin'): ?><a class="button button-secondary" href="/admin">Admin dashboard</a>
        <?php else: ?><a class="button button-secondary" href="/login">Login</a><?php endif; ?>
    </div>
</section>
