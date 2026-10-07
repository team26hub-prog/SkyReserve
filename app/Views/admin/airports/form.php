<?php $record = $data['record']; $editing = !empty($record['id']); ?>
<h1><?= $escape($data['title']) ?></h1>
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
    <button type="submit"><?= $editing ? 'Save changes' : 'Add airport' ?></button>
</form>
<p><a href="/admin/airports">Cancel and return to airports</a></p>
