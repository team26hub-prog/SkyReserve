<div class="page-heading"><h1>Aircraft</h1><a class="button" href="/admin/aircraft/create">Add aircraft</a></div>
<p class="muted">Manage each aircraft’s capacity and seats. Remove its seats before deleting an aircraft.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<?php if (!$data['aircraft']): ?>
    <p class="empty-state">No aircraft yet. Add an aircraft, then configure its seats.</p>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Aircraft list">
        <table>
            <thead><tr><th scope="col">Name/model</th><th scope="col">Registration</th><th scope="col">Seats / capacity</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($data['aircraft'] as $aircraft): ?>
                <tr>
                    <td><?= $escape($aircraft['model']) ?></td><td><?= $escape($aircraft['registration_number']) ?></td><td><?= (int) $aircraft['seat_count'] ?> / <?= (int) $aircraft['total_capacity'] ?></td>
                    <td><div class="row-actions">
                        <a href="/admin/seats?aircraft_id=<?= (int) $aircraft['id'] ?>">Seats</a>
                        <a href="/admin/aircraft/edit?id=<?= (int) $aircraft['id'] ?>">Edit</a>
                        <form action="/admin/aircraft/delete?id=<?= (int) $aircraft['id'] ?>" method="post" data-confirm="Delete this aircraft? Its seats must be removed first.">
                            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button class="button-danger" type="submit">Delete</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
