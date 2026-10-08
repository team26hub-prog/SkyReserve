<span class="eyebrow">Operations · Destinations</span>
<div class="page-heading"><h1>Airports</h1><a class="button" href="/admin/airports/create">Add airport</a></div>
<p class="muted">Manage the airports that connect your airline's routes.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<?php if (!$data['airports']): ?>
    <p class="empty-state">No airports yet. Add your first airport.</p>
<?php else: ?>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Airport list">
        <table>
            <caption class="sr-only">Airport directory and management actions</caption>
            <thead><tr><th scope="col">Code</th><th scope="col">Airport name</th><th scope="col">City</th><th scope="col">Country</th><th scope="col">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($data['airports'] as $airport): ?>
                <tr>
                    <td><?= $escape($airport['iata_code']) ?></td><td><?= $escape($airport['name']) ?></td><td><?= $escape($airport['city']) ?></td><td><?= $escape($airport['country']) ?></td>
                    <td><div class="row-actions">
                        <a href="/admin/airports/edit?id=<?= (int) $airport['id'] ?>">Edit</a>
                        <form action="/admin/airports/delete?id=<?= (int) $airport['id'] ?>" method="post" data-confirm="Delete this airport?">
                            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button class="button-danger" type="submit">Delete</button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
