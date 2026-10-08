<?php $booking = $data['booking']; $flight = $data['flight']; ?>
<h1>Booking summary</h1>
<dl class="profile-details">
    <dt>Booking reference / PNR</dt><dd><strong><?= $escape($booking['booking_reference']) ?></strong></dd>
    <dt>Booking status</dt><dd><?= $booking['status'] === 'pending' ? 'Pending Payment' : $escape(ucfirst($booking['status'])) ?></dd>
    <dt>Flight</dt><dd><?= $escape($flight['flight_number']) ?></dd>
    <dt>Route</dt><dd><?= $escape($flight['origin_name'] . ' (' . $flight['origin_code'] . ') → ' . $flight['destination_name'] . ' (' . $flight['destination_code'] . ')') ?></dd>
    <dt>Departure (UTC)</dt><dd><?= $escape($flight['departure_at']) ?></dd>
    <dt>Arrival (UTC)</dt><dd><?= $escape($flight['arrival_at']) ?></dd>
    <dt>Fare at booking</dt><dd><?= $escape($booking['currency'] . ' ' . $booking['total_amount']) ?></dd>
</dl>
<?php foreach ($data['passengers'] as $passenger): ?>
    <h2>Passenger</h2><dl class="profile-details">
        <dt>Full name</dt><dd><?= $escape($passenger['full_name'] ?? trim($passenger['first_name'] . ' ' . $passenger['last_name'])) ?></dd>
        <dt>CNIC / Passport</dt><dd><?= $escape($passenger['document_number'] ?? '') ?></dd>
        <dt>Date of birth</dt><dd><?= $escape($passenger['date_of_birth'] ?? '') ?></dd>
        <dt>Gender</dt><dd><?= $escape(ucfirst($passenger['gender'] ?? '')) ?></dd>
        <dt>Phone</dt><dd><?= $escape($passenger['phone'] ?? '') ?></dd>
    </dl>
<?php endforeach; ?>
<p><a href="/flights">Search flights</a></p>
