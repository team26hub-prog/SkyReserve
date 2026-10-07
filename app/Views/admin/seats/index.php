<?php $aircraft = $data['aircraft']; $aircraftId = (int) $aircraft['id']; ?>
<div class="page-heading"><h1>Aircraft seats</h1><a class="button" href="/admin/seats/create?aircraft_id=<?= $aircraftId ?>">Add seat</a></div>
<p><strong><?= $escape($aircraft['model']) ?></strong> · <?= $escape($aircraft['registration_number']) ?></p>
<p><?= (int) $aircraft['seat_count'] ?> of <?= (int) $aircraft['total_capacity'] ?> seats configured. <a href="/admin/aircraft/edit?id=<?= $aircraftId ?>">Edit capacity</a></p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<?php if (!$data['seats']): ?>
    <p class="empty-state">No seats configured for this aircraft.</p>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Seat list">
        <table>
            <thead><tr><th scope="col">Seat number</th><th scope="col">Seat class</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($data['seats'] as $seat): ?>
                <tr>
                    <td><?= $escape($seat['seat_number']) ?></td><td><?= $escape(ucfirst($seat['cabin_class'])) ?></td>
                    <td><div class="row-actions">
                        <a href="/admin/seats/edit?aircraft_id=<?= $aircraftId ?>&amp;id=<?= (int) $seat['id'] ?>">Edit</a>
                        <form action="/admin/seats/delete?aircraft_id=<?= $aircraftId ?>&amp;id=<?= (int) $seat['id'] ?>" method="post" data-confirm="Delete this seat?">
                            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button class="button-danger" type="submit">Delete</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<p><a href="/admin/aircraft">Return to aircraft</a></p>
