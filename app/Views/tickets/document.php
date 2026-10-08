<?php $name = $ticket['full_name'] ?? trim($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
<article class="ticket-document" aria-label="SkyReserve passenger ticket">
    <header class="ticket-heading"><div><p class="ticket-brand">Sky<span>Reserve</span></p><h1>Electronic ticket</h1></div><div><span class="ticket-label">Ticket number</span><strong><?= $escape($ticket['ticket_number']) ?></strong></div></header>
    <div class="ticket-route"><div><span class="ticket-label">Departure</span><strong><?= $escape($ticket['origin_code']) ?></strong><p><?= $escape($ticket['origin_name']) ?></p></div><span aria-hidden="true">→</span><div><span class="ticket-label">Arrival</span><strong><?= $escape($ticket['destination_code']) ?></strong><p><?= $escape($ticket['destination_name']) ?></p></div></div>
    <dl class="ticket-grid">
        <div><dt>Booking reference / PNR</dt><dd><?= $escape($ticket['booking_reference']) ?></dd></div>
        <div><dt>Booking status</dt><dd><?= $escape(\App\Models\Booking::statusLabel($ticket['booking_status'])) ?></dd></div>
        <div><dt>Passenger name</dt><dd><?= $escape($name) ?></dd></div>
        <div><dt>CNIC / Passport</dt><dd><?= $escape($ticket['document_number'] ?? '') ?></dd></div>
        <div><dt>Flight number</dt><dd><?= $escape($ticket['flight_number']) ?></dd></div>
        <div><dt>Seat and class</dt><dd><?= $escape($ticket['seat_number'] . ' / ' . ucfirst($ticket['cabin_class'])) ?></dd></div>
        <div><dt>Departure (UTC)</dt><dd><?= $escape($ticket['departure_at']) ?></dd></div>
        <div><dt>Arrival (UTC)</dt><dd><?= $escape($ticket['arrival_at']) ?></dd></div>
        <div><dt>Fare at booking</dt><dd><?= $escape($ticket['currency'] . ' ' . $ticket['total_amount']) ?></dd></div>
        <div><dt>Ticket status</dt><dd><?= $escape(ucfirst($ticket['status'])) ?></dd></div>
        <div><dt>Issued at (UTC)</dt><dd><?= $escape($ticket['issued_at']) ?></dd></div>
    </dl>
    <?php if ($ticket['status'] === 'void'): ?><p class="ticket-invalid" role="status">VOID TICKET — This ticket is no longer valid for travel.</p><?php endif; ?>
    <footer class="ticket-footer">Thank you for choosing SkyReserve. All times shown are UTC.</footer>
</article>
