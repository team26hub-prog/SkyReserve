<?php
$mobileRole = $data['user']['role'] ?? null;
$mobileLinks = [
    ['/', 'Home', 'm3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7', $currentPath === '/', 'Home'],
    ['/flights', 'Flights', 'm3 13 7 2 10-10 1 1-7 13-3-6-8-1ZM10 15l-4 5', str_starts_with($currentPath, '/flights'), 'Search flights'],
];
if ($mobileRole === 'admin') {
    $mobileLinks = [];
    foreach ($adminLinks as $path => [$label, $symbol]) {
        $active = $path === '/admin' ? $currentPath === $path : ($currentPath === $path || str_starts_with($currentPath, $path . '/') || ($path === '/admin/aircraft' && str_starts_with($currentPath, '/admin/seats')) || ($path === '/admin/payments' && str_starts_with($currentPath, '/admin/tickets/')));
        $mobileLinks[] = [$path, $label, $symbol, $active, $label];
    }
} elseif ($mobileRole === 'customer') {
    $mobileLinks = array_map(static fn (array $link): array => [$link[0], $link[1], $link[2], $link[3], $link[1]], $customerLinks);
} else {
    $mobileLinks[] = [$mobileRole === 'customer' ? '/bookings' : '/login', 'Bookings', 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h5', str_starts_with($currentPath, '/bookings') || str_starts_with($currentPath, '/tickets'), $mobileRole === 'customer' ? 'My bookings' : 'Log in to view your bookings'];
    $mobileLinks[] = [$mobileRole === 'customer' ? '/profile' : '/login', 'Profile', 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM4 21v-2a8 8 0 0 1 16 0v2', in_array($currentPath, ['/profile', '/login', '/register'], true), $mobileRole === 'customer' ? 'Your profile' : 'Log in to view your profile'];
}
?>
<nav class="mobile-quick-nav" aria-label="Quick navigation" style="--quick-link-count: <?= count($mobileLinks) ?>">
    <?php foreach ($mobileLinks as [$href, $label, $icon, $active, $accessibleLabel]): ?>
        <a href="<?= $escape($href) ?>" aria-label="<?= $escape($accessibleLabel) ?>" title="<?= $escape($accessibleLabel) ?>"<?= $active ? ' aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php if ($mobileRole === 'admin'): ?><?= $icon ?><?php else: ?><path d="<?= $escape($icon) ?>"/><?php endif; ?></svg></a>
    <?php endforeach; ?>
</nav>
