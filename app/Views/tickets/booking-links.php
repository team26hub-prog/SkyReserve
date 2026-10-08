<section aria-label="Booking tickets">
    <h2>Tickets</h2>
    <?php if ($data['tickets']): ?>
        <ul><?php foreach ($data['tickets'] as $issuedTicket): ?><li><a href="<?= $ticketPrefix ?>/tickets/show?id=<?= (int) $issuedTicket['id'] ?>">View ticket <?= $escape($issuedTicket['ticket_number']) ?></a></li><?php endforeach; ?></ul>
    <?php elseif ($ticketEligible): ?>
        <p class="muted">Your payment is verified. Each passenger needs a valid assigned seat before tickets can be issued.</p>
        <form method="post" action="<?= $ticketPrefix ?>/bookings/tickets?booking_id=<?= $ticketBookingId ?>">
            <input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button type="submit">Generate ticket</button>
        </form>
    <?php else: ?>
        <p class="muted">Tickets become available after booking confirmation, verified payment, and seat assignment.</p>
    <?php endif; ?>
</section>
