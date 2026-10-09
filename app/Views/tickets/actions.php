<?php $ticket = $data['ticket']; require BASE_PATH . '/app/Views/tickets/document.php'; ?>
<div class="ticket-actions">
    <button type="button" data-print-ticket>Print ticket</button>
    <a class="button button-secondary" href="<?= $data['ticketPrefix'] ?>/tickets/download?id=<?= (int) $ticket['id'] ?>" download="SkyReserve-ticket-<?= (int) $ticket['id'] ?>.pdf">Download PDF</a>
    <a class="button button-secondary" href="<?= $data['ticketPrefix'] ? '/admin/payments?status=verified' : '/bookings/show?id=' . (int) $ticket['booking_id'] ?>">Return to <?= $data['ticketPrefix'] ? 'payments' : 'booking' ?></a>
    <p class="muted">Print your ticket or download a PDF to save on your device and view offline.</p>
</div>
