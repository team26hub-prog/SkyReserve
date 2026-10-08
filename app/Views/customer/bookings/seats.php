<?php $booking = $data['booking']; $assigned = array_column($data['assignments'], 'passenger_id'); ?>
<h1>Select a seat</h1>
<p><?= $escape($booking['booking_reference'] . ' — ' . $data['flight']['flight_number'] . ' — ' . $data['flight']['aircraft_model']) ?></p>
<?php if ($data['error']): ?><div class="notice error" role="alert"><?= $escape($data['error']) ?></div><?php endif; ?>
<p class="seat-legend"><span>Available</span><span class="legend-selected">Selected for this booking</span><span class="legend-unavailable">Booked / unavailable</span></p>
<form action="/bookings/seats?booking_id=<?= (int) $booking['id'] ?>" method="post" class="account-form">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="passenger_id">Passenger</label>
    <select id="passenger_id" name="passenger_id" required><option value="">Choose passenger</option>
        <?php foreach ($data['passengers'] as $passenger): ?>
            <option value="<?= (int) $passenger['id'] ?>"<?= in_array($passenger['id'], $assigned) || $passenger['status'] !== 'active' ? ' disabled' : '' ?>><?= $escape($passenger['full_name'] ?? trim($passenger['first_name'] . ' ' . $passenger['last_name'])) ?><?= in_array($passenger['id'], $assigned) ? ' (seat already assigned)' : '' ?></option>
        <?php endforeach; ?>
    </select>
    <fieldset class="seat-map"><legend>Aircraft seats</legend><div class="seat-grid">
        <?php foreach ($data['seats'] as $seat):
            $own = $seat['occupant_booking_id'] !== null && (int) $seat['occupant_booking_id'] === (int) $booking['id'];
            $unavailable = $seat['status'] !== 'active' || $seat['occupant_booking_id'] !== null;
            $state = $own ? 'Selected' : ($unavailable ? 'Booked / unavailable' : 'Available'); ?>
            <label class="seat-tile<?= $own ? ' selected-seat' : ($unavailable ? ' unavailable-seat' : '') ?>">
                <input type="radio" name="seat_id" value="<?= (int) $seat['id'] ?>" required<?= $unavailable ? ' disabled' : '' ?>>
                <strong><?= $escape($seat['seat_number']) ?></strong><small><?= $escape(ucfirst($seat['cabin_class'])) ?></small><small><?= $state ?></small>
            </label>
        <?php endforeach; ?>
    </div><?php if (!$data['seats']): ?><p>No seats are configured for this aircraft.</p><?php endif; ?></fieldset>
    <button type="submit"<?= count($assigned) >= count($data['passengers']) ? ' disabled' : '' ?>>Save selected seat</button>
</form>
<p><a href="/bookings/show?id=<?= (int) $booking['id'] ?>">Return to booking summary</a></p>
