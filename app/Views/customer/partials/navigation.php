<?php
$customerSection = $data['section'] ?? 'bookings';
$customerLinks = [
    ['/', 'Home', 'm3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7', $currentPath === '/'],
    ['/flights', 'Search flights', 'm21 3-6 18-4-8-8-4L21 3Zm-10 10L21 3', str_starts_with($currentPath, '/flights')],
    ['/bookings', 'My bookings', 'M5 4h14v17H5zM9 4V2m6 2V2M8 9h8M8 13h8M8 17h5', ($currentPath === '/bookings' && $customerSection === 'bookings') || in_array($currentPath, ['/bookings/show', '/bookings/create'], true)],
    ['/bookings?section=seats', 'Seats', 'M7 3v9h10V3M5 12v6h14v-6M7 18v3m10-3v3M7 8h10', ($currentPath === '/bookings' && $customerSection === 'seats') || $currentPath === '/bookings/seats'],
    ['/bookings?section=payments', 'Payments', 'M3 5h18v14H3zM3 10h18M7 15h3', ($currentPath === '/bookings' && $customerSection === 'payments') || in_array($currentPath, ['/bookings/payment', '/payments/receipt'], true)],
    ['/bookings?section=tickets', 'E-tickets', 'M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4V5Zm12 0v3m0 3v2m0 3v3', ($currentPath === '/bookings' && $customerSection === 'tickets') || str_starts_with($currentPath, '/tickets/')],
    ['/profile', 'My profile', 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2', $currentPath === '/profile'],
];
?>
<nav class="admin-nav customer-nav" aria-label="Customer navigation">
    <?php foreach ($customerLinks as [$path, $label, $symbol, $active]): ?>
    <a href="<?= $escape($path) ?>"<?= $active ? ' aria-current="page"' : '' ?>><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="<?= $escape($symbol) ?>"/></svg><?= $escape($label) ?></a>
    <?php endforeach; ?>
</nav>
