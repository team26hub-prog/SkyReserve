<?php $record = $data['record']; $editing = !empty($record['id']); ?>
<span class="eyebrow">Operations · Airport information</span>
<h1><?= $escape($data['title']) ?></h1>
<p class="muted">Keep destination details clear and consistent. Fields marked * are required.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<form class="account-form" action="<?= $editing ? '/admin/airports/update?id=' . (int) $record['id'] : '/admin/airports' ?>" method="post">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="iata_code">Airport code</label>
    <input id="iata_code" name="iata_code" maxlength="3" minlength="3" pattern="[A-Za-z]{3}" value="<?= $escape($record['iata_code'] ?? '') ?>" aria-describedby="code-help" required>
    <small id="code-help">Three-letter airport code, for example LHE.</small>
    <label for="name">Airport name</label>
    <input id="name" name="name" maxlength="150" value="<?= $escape($record['name'] ?? '') ?>" required>
    <label for="city">City</label>
    <input id="city" name="city" maxlength="100" value="<?= $escape($record['city'] ?? '') ?>" required>
    <label for="country">Country</label>
    <input id="country" name="country" maxlength="100" value="<?= $escape($record['country'] ?? '') ?>" required>
    <div class="actions form-actions">
        <button type="submit"><?= $editing ? 'Save changes' : 'Add airport' ?></button>
        <a class="button button-secondary" href="/admin/airports">Cancel and return to airports</a>
    </div>
</form>
