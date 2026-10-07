<?php $flight = $data['flight']; ?>
<h1>Flight <?= $escape($flight['flight_number']) ?></h1>
<p><?= $escape($flight['origin_code']) ?> → <?= $escape($flight['destination_code']) ?></p>
<dl class="profile-details">
    <dt>Departure airport</dt><dd><?= $escape($flight['origin_name'] . ' (' . $flight['origin_code'] . '), ' . $flight['origin_city']) ?></dd>
    <dt>Arrival airport</dt><dd><?= $escape($flight['destination_name'] . ' (' . $flight['destination_code'] . '), ' . $flight['destination_city']) ?></dd>
    <dt>Aircraft</dt><dd><?= $escape($flight['aircraft_model'] . ' — ' . $flight['registration_number']) ?></dd>
    <dt>Capacity</dt><dd><?= (int) $flight['total_capacity'] ?></dd>
    <dt>Departure (UTC)</dt><dd><?= $escape($flight['departure_at']) ?></dd>
    <dt>Arrival (UTC)</dt><dd><?= $escape($flight['arrival_at']) ?></dd>
    <dt>Fare</dt><dd><?= $escape($flight['currency'] . ' ' . $flight['base_fare']) ?></dd>
    <dt>Status</dt><dd><?= $escape(ucfirst($flight['status'])) ?></dd>
    <dt>Created (UTC)</dt><dd><?= $escape($flight['created_at']) ?></dd>
    <dt>Updated (UTC)</dt><dd><?= $escape($flight['updated_at']) ?></dd>
</dl>
<div class="actions">
    <a class="button" href="/admin/flights/edit?id=<?= (int) $flight['id'] ?>">Edit flight</a>
    <form action="/admin/flights/delete?id=<?= (int) $flight['id'] ?>" method="post" data-confirm="Delete this flight?">
        <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button class="button-danger" type="submit">Delete flight</button>
    </form>
    <a href="/admin/flights">Return to flights</a>
</div>
