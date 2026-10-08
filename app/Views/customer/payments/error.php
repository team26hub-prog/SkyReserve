<span class="eyebrow">Your booking · Payment information</span>
<h1><?= $escape($data['title']) ?></h1>
<p><?= $escape($data['message']) ?></p>
<a class="button button-secondary" href="/bookings/show?id=<?= (int) $data['bookingId'] ?>">Return to booking summary</a>
