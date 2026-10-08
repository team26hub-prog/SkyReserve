<?php
// Explicit parent pages keep navigation safe even when a page is opened directly.
$backUrl = '/'; $backLabel = 'Back to home';
if (str_starts_with($view, 'admin/')) {
    $backUrl = '/admin'; $backLabel = 'Back to dashboard';
    foreach (['airports','aircraft','flights','payments','cancellations','reports'] as $section) {
        if (str_starts_with($view, 'admin/' . $section . '/') && !str_ends_with($view, '/index')) {
            $backUrl = '/admin/' . $section; $backLabel = 'Back to ' . $section; break;
        }
    }
    if (str_starts_with($view, 'admin/seats/')) {
        $backUrl = $view === 'admin/seats/index' ? '/admin/aircraft' : '/admin/seats?aircraft_id=' . (int) ($data['aircraft']['id'] ?? 0);
        $backLabel = $view === 'admin/seats/index' ? 'Back to aircraft' : 'Back to seats';
    }
    if (str_starts_with($view, 'admin/tickets/')) { $backUrl = '/admin/payments?status=verified'; $backLabel = 'Back to payments'; }
} elseif ($view === 'customer/flights/show') {
    $backUrl = $data['back']; $backLabel = 'Back to matching flights';
} elseif ($view === 'customer/bookings/form') {
    $backUrl = '/flights/show?id=' . (int) $data['flight']['id']; $backLabel = 'Back to flight details';
} elseif (str_starts_with($view, 'customer/bookings/') || str_starts_with($view, 'customer/payments/') || str_starts_with($view, 'customer/cancellations/')) {
    $backUrl = '/bookings'; $backLabel = 'Back to My Bookings';
    if (!in_array($view, ['customer/bookings/index','customer/bookings/show'], true) && isset($data['booking']['id'])) {
        $backUrl = '/bookings/show?id=' . (int) $data['booking']['id']; $backLabel = 'Back to booking summary';
    }
} elseif ($view === 'customer/tickets/show') {
    $backUrl = '/bookings/show?id=' . (int) $data['ticket']['booking_id']; $backLabel = 'Back to booking summary';
}
?>
<?php if ($view === 'admin/index') { $backUrl = '/'; $backLabel = 'Back to home'; } ?>
<?php if ($view !== 'foundation/index'): ?>
<nav class="back-navigation" aria-label="Page navigation"><a class="button button-secondary back-link" href="<?= $escape($backUrl) ?>"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h14"/></svg><?= $escape($backLabel) ?></a></nav>
<?php endif; ?>
