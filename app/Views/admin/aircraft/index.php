<?php $actionIcons = require BASE_PATH . '/app/Views/admin/partials/action-icons.php'; ?>
<span class="eyebrow">Operations · Fleet</span>
<div class="page-heading"><h1>Aircraft</h1><a aria-label="Add aircraft" title="Add aircraft" class="mobile-icon-button button" href="/admin/aircraft/create"><?= $actionIcons['add'] ?><span class="mobile-action-label">Add aircraft</span></a></div>
<p class="muted">Manage each aircraft’s capacity and seats. Remove its seats before deleting an aircraft.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<?php if (!$data['aircraft']): ?>
    <p class="empty-state">No aircraft yet. Add an aircraft, then configure its seats.</p>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Aircraft list">
        <table>
            <caption class="sr-only">Aircraft fleet, seat capacity, and management actions</caption>
            <thead><tr><th scope="col">Name/model</th><th scope="col">Registration</th><th scope="col">Seats / capacity</th><th class="action-column" scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($data['aircraft'] as $aircraft): ?>
                <tr>
                    <td><?= $escape($aircraft['model']) ?></td><td><?= $escape($aircraft['registration_number']) ?></td><td><?= (int) $aircraft['seat_count'] ?> / <?= (int) $aircraft['total_capacity'] ?></td>
                    <td><div class="row-actions mobile-action-row">
                        <a aria-label="Seats" title="Seats" class="mobile-icon-button button button-secondary" href="/admin/seats?aircraft_id=<?= (int) $aircraft['id'] ?>"><?= $actionIcons['seats'] ?><span class="mobile-action-label">Seats</span></a>
                        <a aria-label="Edit" title="Edit" class="mobile-icon-button button button-secondary" href="/admin/aircraft/edit?id=<?= (int) $aircraft['id'] ?>"><?= $actionIcons['edit'] ?><span class="mobile-action-label">Edit</span></a>
                        <form action="/admin/aircraft/delete?id=<?= (int) $aircraft['id'] ?>" method="post" data-confirm="Delete this aircraft? Its seats must be removed first.">
                            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button aria-label="Delete" title="Delete" class="mobile-icon-button button-danger" type="submit"><?= $actionIcons['delete'] ?><span class="mobile-action-label">Delete</span></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
