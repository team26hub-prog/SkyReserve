<?php $record = $data['record']; $editing = !empty($record['id']); ?>
<span class="eyebrow">Operations · Aircraft information</span>
<h1><?= $escape($data['title']) ?></h1>
<p class="muted">Update your fleet information and cabin capacity. Fields marked * are required.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<form class="account-form" action="<?= $editing ? '/admin/aircraft/update?id=' . (int) $record['id'] : '/admin/aircraft' ?>" method="post">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="model">Aircraft name/model</label>
    <input id="model" name="model" maxlength="100" value="<?= $escape($record['model'] ?? '') ?>" required>
    <label for="registration_number">Registration number</label>
    <input id="registration_number" name="registration_number" maxlength="20" pattern="[A-Za-z0-9][A-Za-z0-9\-]{0,19}" value="<?= $escape($record['registration_number'] ?? '') ?>" aria-describedby="registration-help" required>
    <small id="registration-help">Letters, digits, and hyphens; for example AP-ABC.</small>
    <label for="total_capacity">Total capacity</label>
    <input id="total_capacity" name="total_capacity" type="number" min="1" max="4294967295" step="1" value="<?= $escape((string) ($record['total_capacity'] ?? '')) ?>" required>
    <?php if (isset($record['seat_count'])): ?><small>Currently configured seats: <?= (int) $record['seat_count'] ?>.</small><?php endif; ?>
    <div class="actions form-actions">
        <button type="submit"><?= $editing ? 'Save changes' : 'Add aircraft' ?></button>
        <a class="button button-secondary" href="/admin/aircraft">Cancel and return to aircraft</a>
    </div>
</form>
