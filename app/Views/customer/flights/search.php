<h1>Search flights</h1>
<p class="muted">Choose your route and travel date. All dates and times use UTC.</p>
<?php if ($data['errors']): ?>
    <div class="notice error" role="alert"><ul><?php foreach ($data['errors'] as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (count($data['airports']) < 2): ?><p class="empty-state">Flight routes are not available yet. Please check again later.</p><?php endif; ?>
<form class="search-form" action="/flights" method="get">
    <?php foreach (['from_airport_id' => 'From airport', 'to_airport_id' => 'To airport'] as $key => $label): ?>
        <div><label for="<?= $key ?>"><?= $label ?></label>
        <select id="<?= $key ?>" name="<?= $key ?>" required>
            <option value="">Choose an airport</option>
            <?php foreach ($data['airports'] as $airport): ?>
                <option value="<?= (int) $airport['id'] ?>"<?= (string) $data['values'][$key] === (string) $airport['id'] ? ' selected' : '' ?>><?= $escape($airport['iata_code'] . ' — ' . $airport['city'] . ' (' . $airport['name'] . ')') ?></option>
            <?php endforeach; ?>
        </select></div>
    <?php endforeach; ?>
    <div><label for="travel_date">Travel date (UTC)</label><input type="date" id="travel_date" name="travel_date" min="<?= $data['today'] ?>" max="9999-12-31" value="<?= $escape($data['values']['travel_date']) ?>" required></div>
    <button type="submit"<?= count($data['airports']) < 2 ? ' disabled' : '' ?>>Search flights</button>
</form>
<?php if ($data['submitted'] && !$data['errors']): ?>
    <h2>Search results</h2>
    <?php if (!$data['flights']): ?>
        <p class="empty-state" role="status">No available scheduled flights match this route and date. Try another travel date or route.</p>
    <?php else: ?>
        <p role="status"><?= count($data['flights']) ?> flight(s) found. Seat availability may change.</p>
        <div class="flight-results">
        <?php foreach ($data['flights'] as $flight): ?>
            <article class="flight-card">
                <div class="page-heading"><h3><?= $escape($flight['flight_number']) ?></h3><span><?= $escape(ucfirst($flight['status'])) ?></span></div>
                <p><strong><?= $escape($flight['origin_code'] . ' → ' . $flight['destination_code']) ?></strong></p>
                <dl class="flight-summary">
                    <dt>Departure airport</dt><dd><?= $escape($flight['origin_name'] . ' (' . $flight['origin_city'] . ')') ?></dd>
                    <dt>Arrival airport</dt><dd><?= $escape($flight['destination_name'] . ' (' . $flight['destination_city'] . ')') ?></dd>
                    <dt>Departure (UTC)</dt><dd><?= $escape($flight['departure_at']) ?></dd>
                    <dt>Arrival (UTC)</dt><dd><?= $escape($flight['arrival_at']) ?></dd>
                    <dt>Aircraft</dt><dd><?= $escape($flight['aircraft_model']) ?></dd>
                    <dt>Fare</dt><dd><?= $escape($flight['currency'] . ' ' . $flight['base_fare']) ?></dd>
                    <dt>Available seats</dt><dd><?= (int) $flight['available_seats'] ?></dd>
                </dl>
                <a class="button" href="/flights/show?id=<?= (int) $flight['id'] ?>">View flight</a>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
