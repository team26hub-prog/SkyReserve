<?php $record = $data['record']; $editing = !empty($record['id']); $aircraftId = (int) $data['aircraft']['id']; ?>
<span class="eyebrow">Operations · Seat information</span>
<h1><?= $escape($data['title']) ?></h1>
<p><?= $escape($data['aircraft']['model']) ?> · <?= $escape($data['aircraft']['registration_number']) ?></p>
<p class="muted"><?= (int) $data['aircraft']['seat_count'] ?> of <?= (int) $data['aircraft']['total_capacity'] ?> seats configured.</p>
<?php require BASE_PATH . '/app/Views/admin/partials/errors.php'; ?>
<form class="account-form" action="<?= $editing ? '/admin/seats/update?aircraft_id=' . $aircraftId . '&amp;id=' . (int) $record['id'] : '/admin/seats?aircraft_id=' . $aircraftId ?>" method="post">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="seat_number">Seat number</label>
    <input id="seat_number" name="seat_number" maxlength="8" pattern="[A-Za-z0-9][A-Za-z0-9\-]{0,7}" value="<?= $escape($record['seat_number'] ?? '') ?>" aria-describedby="seat-help" required>
    <small id="seat-help">Unique on this aircraft, for example 12A.</small>
    <label for="cabin_class">Seat class</label>
    <select id="cabin_class" name="cabin_class" required>
        <option value="">Choose seat class</option>
        <option value="economy"<?= ($record['cabin_class'] ?? '') === 'economy' ? ' selected' : '' ?>>Economy</option>
        <option value="business"<?= ($record['cabin_class'] ?? '') === 'business' ? ' selected' : '' ?>>Business</option>
    </select>
    <button type="submit"><?= $editing ? 'Save changes' : 'Add seat' ?></button>
</form>
<p><a href="/admin/seats?aircraft_id=<?= $aircraftId ?>">Cancel and return to seats</a></p>
