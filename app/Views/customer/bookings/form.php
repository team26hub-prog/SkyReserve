<?php $flight = $data['flight']; $values = $data['values']; ?>
<span class="eyebrow">Your booking · Passenger information</span>
<h1>Passenger details</h1>
<div class="card booking-context"><p><strong><?= $escape($flight['flight_number'] . ' — ' . $flight['origin_code'] . ' → ' . $flight['destination_code']) ?></strong></p>
<p>Departure: <?= $escape($flight['departure_at']) ?> UTC<br>Fare: <?= $escape($flight['currency'] . ' ' . $flight['base_fare']) ?></p></div>
<p class="muted">Enter passenger details as they appear on the travel document. Fields marked * are required.</p>
<?php if ($data['errors']): ?><div class="notice error" role="alert"><ul><?php foreach ($data['errors'] as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="account-form" action="/bookings?flight_id=<?= (int) $flight['id'] ?>" method="post">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <input type="hidden" name="booking_token" value="<?= $escape($data['booking_token']) ?>">
    <label for="full_name">Passenger full name</label>
    <input id="full_name" name="full_name" autocomplete="name" maxlength="160" value="<?= $escape($values['full_name'] ?? '') ?>" required>
    <label for="document_number">CNIC / Passport number</label>
    <input id="document_number" name="document_number" maxlength="20" value="<?= $escape($values['document_number'] ?? '') ?>" aria-describedby="document-help" required>
    <small id="document-help">13-digit CNIC (hyphens optional), or an alphanumeric passport number.</small>
    <label for="date_of_birth">Date of birth</label>
    <input id="date_of_birth" name="date_of_birth" type="date" autocomplete="bday" min="1900-01-01" max="<?= $data['today'] ?>" value="<?= $escape($values['date_of_birth'] ?? '') ?>" required>
    <label for="gender">Gender</label>
    <select id="gender" name="gender" required><option value="">Choose gender</option>
        <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $key => $label): ?><option value="<?= $key ?>"<?= ($values['gender'] ?? '') === $key ? ' selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
    </select>
    <label for="phone">Passenger phone number</label>
    <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="<?= $escape($values['phone'] ?? '') ?>" required>
    <button type="submit">Create booking</button>
</form>
<p><a class="button button-secondary" href="/flights/show?id=<?= (int) $flight['id'] ?>">Return to flight details</a></p>
