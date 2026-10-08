<span class="eyebrow">Operations · Flight schedule</span>
<div class="page-heading"><h1>Flights</h1><a class="button" href="/admin/flights/create">Add flight</a></div>
<p class="muted">All departure and arrival times are shown in UTC.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<?php if (!$data['flights']): ?>
    <p class="empty-state">No flights yet. Add a flight using your configured airports and aircraft.</p>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Flight list">
        <table class="flight-table">
            <caption class="sr-only">Flight schedules, fares, statuses, and management actions</caption>
            <thead><tr><th scope="col">Flight</th><th scope="col">Route</th><th scope="col">Aircraft</th><th scope="col">Departure (UTC)</th><th scope="col">Arrival (UTC)</th><th scope="col">Fare</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($data['flights'] as $flight): ?>
                <tr>
                    <td><?= $escape($flight['flight_number']) ?></td>
                    <td><?= $escape($flight['origin_code']) ?> → <?= $escape($flight['destination_code']) ?><small class="cell-note"><?= $escape($flight['origin_city']) ?> → <?= $escape($flight['destination_city']) ?></small></td>
                    <td><?= $escape($flight['aircraft_model']) ?><small class="cell-note"><?= $escape($flight['registration_number']) ?></small></td>
                    <td><?= $escape($flight['departure_at']) ?></td><td><?= $escape($flight['arrival_at']) ?></td>
                    <td class="fare"><?= $escape($flight['currency'] . ' ' . $flight['base_fare']) ?></td><td><span class="badge" data-status="<?= $escape($flight['status']) ?>"><?= $escape(ucfirst($flight['status'])) ?></span></td>
                    <td><div class="row-actions">
                        <a class="button button-secondary" href="/admin/flights/show?id=<?= (int) $flight['id'] ?>">View</a>
                        <a class="button button-secondary" href="/admin/flights/edit?id=<?= (int) $flight['id'] ?>">Edit</a>
                        <form action="/admin/flights/delete?id=<?= (int) $flight['id'] ?>" method="post" data-confirm="Delete this flight?">
                            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button class="button-danger" type="submit">Delete</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
