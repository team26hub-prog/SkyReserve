<?php $flight = $data['flight']; ?>
<h1>Flight <?= $escape($flight['flight_number']) ?></h1>
<p><strong><?= $escape($flight['origin_code'] . ' → ' . $flight['destination_code']) ?></strong></p>
<dl class="profile-details">
    <dt>Departure airport</dt><dd><?= $escape($flight['origin_name'] . ' (' . $flight['origin_code'] . '), ' . $flight['origin_city']) ?></dd>
    <dt>Arrival airport</dt><dd><?= $escape($flight['destination_name'] . ' (' . $flight['destination_code'] . '), ' . $flight['destination_city']) ?></dd>
    <dt>Departure (UTC)</dt><dd><?= $escape($flight['departure_at']) ?></dd>
    <dt>Arrival (UTC)</dt><dd><?= $escape($flight['arrival_at']) ?></dd>
    <dt>Aircraft</dt><dd><?= $escape($flight['aircraft_model']) ?></dd>
    <dt>Fare</dt><dd><?= $escape($flight['currency'] . ' ' . $flight['base_fare']) ?></dd>
    <dt>Available seats</dt><dd><?= (int) $flight['available_seats'] ?></dd>
    <dt>Status</dt><dd><?= $escape(ucfirst($flight['status'])) ?></dd>
</dl>
<p class="muted">Availability is current at the time of viewing and may change.</p>
<?php if (!$data['user'] || $data['user']['role'] === 'customer'): ?>
    <p><a class="button" href="/bookings/create?flight_id=<?= (int) $flight['id'] ?>"><?= $data['user'] ? 'Start booking' : 'Log in to start booking' ?></a></p>
<?php endif; ?>
<a href="<?= $escape($data['back']) ?>">Return to matching flights</a>
