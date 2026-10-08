<?php
$mobileRole = $data['user']['role'] ?? null;
$mobileLinks = [
    ['/', 'Home', 'm3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7', $currentPath === '/', 'Home'],
    ['/flights', 'Flights', 'm3 13 7 2 10-10 1 1-7 13-3-6-8-1ZM10 15l-4 5', str_starts_with($currentPath, '/flights'), 'Search flights'],
];
if ($mobileRole === 'admin') {
    $mobileLinks[] = ['/admin', 'Admin', 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z', str_starts_with($currentPath, '/admin') && !str_starts_with($currentPath, '/admin/reports'), 'Admin dashboard'];
    $mobileLinks[] = ['/admin/reports', 'Reports', 'M4 3v18h17M8 16v-5M13 16V7M18 16V4', str_starts_with($currentPath, '/admin/reports'), 'Admin reports'];
} else {
    $mobileLinks[] = [$mobileRole === 'customer' ? '/bookings' : '/login', 'Bookings', 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5', str_starts_with($currentPath, '/bookings') || str_starts_with($currentPath, '/tickets'), $mobileRole === 'customer' ? 'My bookings' : 'Log in to view your bookings'];
    $mobileLinks[] = [$mobileRole === 'customer' ? '/profile' : '/login', 'Profile', 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2', in_array($currentPath, ['/profile', '/login', '/register'], true), $mobileRole === 'customer' ? 'Your profile' : 'Log in to view your profile'];
}
?>
<nav class="mobile-quick-nav" aria-label="Quick navigation">
    <?php foreach ($mobileLinks as [$href, $label, $icon, $active, $accessibleLabel]): ?>
        <a href="<?= $escape($href) ?>" aria-label="<?= $escape($accessibleLabel) ?>"<?= $active ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= $escape($icon) ?>"/></svg><span><?= $escape($label) ?></span></a>
    <?php endforeach; ?>
</nav>
