<?php $actionIcons = require BASE_PATH . '/app/Views/admin/partials/action-icons.php'; ?>
<span class="eyebrow">Operations · Destinations</span>
<div class="page-heading"><h1>Airports</h1><a aria-label="Add airport" title="Add airport" class="mobile-icon-button button" href="/admin/airports/create"><?= $actionIcons['add'] ?><span class="mobile-action-label">Add airport</span></a></div>
<p class="muted">Manage the airports that connect your airline's routes.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<?php if (!$data['airports']): ?>
    <p class="empty-state">No airports yet. Add your first airport.</p>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Airport list">
        <table>
            <caption class="sr-only">Airport directory and management actions</caption>
            <thead><tr><th scope="col">Code</th><th scope="col">Airport name</th><th scope="col">City</th><th scope="col">Country</th><th class="action-column" scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($data['airports'] as $airport): ?>
                <tr>
                    <td><?= $escape($airport['iata_code']) ?></td><td><?= $escape($airport['name']) ?></td><td><?= $escape($airport['city']) ?></td><td><?= $escape($airport['country']) ?></td>
                    <td><div class="row-actions mobile-action-row">
                        <a aria-label="Edit" title="Edit" class="mobile-icon-button button button-secondary" href="/admin/airports/edit?id=<?= (int) $airport['id'] ?>"><?= $actionIcons['edit'] ?><span class="mobile-action-label">Edit</span></a>
                        <form action="/admin/airports/delete?id=<?= (int) $airport['id'] ?>" method="post" data-confirm="Delete this airport?">
                            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button aria-label="Delete" title="Delete" class="mobile-icon-button button-danger" type="submit"><?= $actionIcons['delete'] ?><span class="mobile-action-label">Delete</span></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
