<?php $name = $ticket['full_name'] ?? trim($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
<?php require BASE_PATH . '/app/Views/tickets/visual-assets.php'; ?>
<article class="ticket-document" aria-label="SkyReserve passenger ticket">
    <header class="ticket-heading"><div><div class="ticket-identity"><span class="ticket-logo"><img src="<?= $ticketVisuals['logo'] ?>" alt="SkyReserve logo"></span><p class="ticket-brand">Sky<span>Reserve</span></p></div><h1>Electronic ticket</h1></div><div class="ticket-number"><span class="ticket-label">Ticket number</span><strong><?= $escape($ticket['ticket_number']) ?></strong></div></header>
    <div class="ticket-route"><div><span class="ticket-label">Departure</span><strong><?= $escape($ticket['origin_code']) ?></strong><p><?= $escape($ticket['origin_name']) ?></p><span class="ticket-route-time"><span class="ticket-label">Departure (UTC)</span><?= $escape($ticket['departure_at']) ?></span></div><span class="ticket-route-plane" aria-hidden="true"><img src="<?= $ticketVisuals['plane'] ?>" alt=""><span>→</span></span><div><span class="ticket-label">Arrival</span><strong><?= $escape($ticket['destination_code']) ?></strong><p><?= $escape($ticket['destination_name']) ?></p><span class="ticket-route-time"><span class="ticket-label">Arrival (UTC)</span><?= $escape($ticket['arrival_at']) ?></span></div></div>
    <dl class="ticket-grid">
        <div><dt>Booking reference / PNR</dt><dd><?= $escape($ticket['booking_reference']) ?></dd></div>
        <div><dt>Booking status</dt><dd><span class="ticket-status"><?= $escape(\App\Models\Booking::statusLabel($ticket['booking_status'])) ?></span></dd></div>
        <div><dt>Passenger name</dt><dd><?= $escape($name) ?></dd></div>
        <div><dt>CNIC / Passport</dt><dd><?= $escape($ticket['document_number'] ?? '') ?></dd></div>
        <div><dt>Flight number</dt><dd><?= $escape($ticket['flight_number']) ?></dd></div>
        <div><dt>Seat and class</dt><dd><?= $escape($ticket['seat_number'] . ' / ' . ucfirst($ticket['cabin_class'])) ?></dd></div>
        <div><dt>Fare at booking</dt><dd><?= $escape($ticket['currency'] . ' ' . $ticket['total_amount']) ?></dd></div>
        <div><dt>Ticket status</dt><dd><span class="ticket-status"><?= $escape(ucfirst($ticket['status'])) ?></span></dd></div>
        <div><dt>Issued at (UTC)</dt><dd><?= $escape($ticket['issued_at']) ?></dd></div>
    </dl>
    <?php if ($ticket['status'] === 'void'): ?><p class="ticket-invalid" role="status">VOID TICKET — This ticket is no longer valid for travel.</p><?php endif; ?>
    <footer class="ticket-footer">
        <?php if ($ticketVisuals['barcode']): ?><div class="ticket-barcode"><img src="<?= $ticketVisuals['barcode'] ?>" alt="Booking reference barcode: <?= $escape($ticket['booking_reference']) ?>"><span class="ticket-label">PNR <?= $escape($ticket['booking_reference']) ?></span></div><?php endif; ?>
        <div><p><strong>Before you travel</strong></p><p>Arrive well before departure and follow the airline's check-in deadline. Carry the travel documents required for your route.</p><p>This electronic ticket records your booking; it is not a boarding pass. Present it at check-in.</p><p>Thank you for choosing SkyReserve. All times shown are UTC.</p></div>
    </footer>
</article>
