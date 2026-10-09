<?php $ticket = $data['ticket']; require BASE_PATH . '/app/Views/tickets/document.php'; ?>
<div class="ticket-actions">
    <button type="button" data-print-ticket>Print ticket</button>
    <a class="button button-secondary" href="<?= $data['ticketPrefix'] ?>/tickets/download?id=<?= (int) $ticket['id'] ?>">Download ticket (HTML)</a>
    <a class="button button-secondary" href="<?= $data['ticketPrefix'] ? '/admin/payments?status=verified' : '/bookings/show?id=' . (int) $ticket['booking_id'] ?>">Return to <?= $data['ticketPrefix'] ? 'payments' : 'booking' ?></a>
    <p class="muted">Use Print ticket to print or save as PDF. The HTML download includes all ticket details and works offline.</p>
</div>
