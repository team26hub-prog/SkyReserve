<?php $record = $data['record']; $editing = !empty($record['id']); $missingReferences = count($data['airports']) < 2 || !$data['aircraft']; ?>
<h1><?= $escape($data['title']) ?></h1>
<p class="muted">Enter departure and arrival times in UTC. Fare currency: <?= $escape($record['currency'] ?? 'PKR') ?>.</p>
<?php if ($missingReferences): ?>
    <div class="notice error" role="alert">Configure at least two <a href="/admin/airports">airports</a> and one <a href="/admin/aircraft">aircraft</a> before adding a flight.</div>
<?php endif; ?>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<form class="account-form" action="<?= $editing ? '/admin/flights/update?id=' . (int) $record['id'] : '/admin/flights' ?>" method="post">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="flight_number">Flight number</label>
    <input id="flight_number" name="flight_number" maxlength="12" pattern="[A-Za-z0-9][A-Za-z0-9\-]{0,11}" value="<?= $escape($record['flight_number'] ?? '') ?>" aria-describedby="number-help" required>
    <small id="number-help">Letters, digits, or hyphens. The same number may recur at a different departure time.</small>
    <?php foreach (['origin_airport_id' => 'Departure airport', 'destination_airport_id' => 'Arrival airport'] as $key => $label): ?>
        <label for="<?= $key ?>"><?= $label ?></label>
        <select id="<?= $key ?>" name="<?= $key ?>" required>
            <option value="">Choose an airport</option>
            <?php foreach ($data['airports'] as $airport): ?>
                <option value="<?= (int) $airport['id'] ?>"<?= (string) ($record[$key] ?? '') === (string) $airport['id'] ? ' selected' : '' ?>><?= $escape($airport['iata_code'] . ' — ' . $airport['name'] . ' (' . $airport['city'] . ')') ?></option>
            <?php endforeach; ?>
        </select>
    <?php endforeach; ?>
    <label for="aircraft_id">Aircraft</label>
    <select id="aircraft_id" name="aircraft_id" required>
        <option value="">Choose an aircraft</option>
        <?php foreach ($data['aircraft'] as $aircraft): ?>
            <option value="<?= (int) $aircraft['id'] ?>"<?= (string) ($record['aircraft_id'] ?? '') === (string) $aircraft['id'] ? ' selected' : '' ?>><?= $escape($aircraft['model'] . ' — ' . $aircraft['registration_number']) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="departure_at">Departure date/time (UTC)</label>
    <input id="departure_at" name="departure_at" type="datetime-local" min="1000-01-01T00:00" max="9999-12-31T23:59:59" step="1" value="<?= $escape(str_replace(' ', 'T', $record['departure_at'] ?? '')) ?>" required>
    <label for="arrival_at">Arrival date/time (UTC)</label>
    <input id="arrival_at" name="arrival_at" type="datetime-local" min="1000-01-01T00:00" max="9999-12-31T23:59:59" step="1" value="<?= $escape(str_replace(' ', 'T', $record['arrival_at'] ?? '')) ?>" required>
    <label for="base_fare">Fare (<?= $escape($record['currency'] ?? 'PKR') ?>)</label>
    <input id="base_fare" name="base_fare" type="number" min="0.01" max="9999999999.99" step="0.01" value="<?= $escape((string) ($record['base_fare'] ?? '')) ?>" required>
    <label for="status">Status</label>
    <select id="status" name="status" required>
        <option value="">Choose a status</option>
        <?php foreach ($data['statuses'] as $value => $label): ?>
            <option value="<?= $value ?>"<?= ($record['status'] ?? '') === $value ? ' selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit"<?= $missingReferences ? ' disabled' : '' ?>><?= $editing ? 'Save changes' : 'Add flight' ?></button>
</form>
<p><a href="/admin/flights">Cancel and return to flights</a></p>
