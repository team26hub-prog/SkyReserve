<?php
$adminLinks = [
    '/admin' => ['Overview', '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
    '/admin/airports' => ['Airports', '<path d="M12 21s7-6 7-12a7 7 0 0 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/>'],
    '/admin/aircraft' => ['Aircraft & seats', '<path d="m21 3-6 18-4-8-8-4L21 3Zm-10 10L21 3"/>'],
    '/admin/flights' => ['Flights', '<path d="M3 7h18m-4-4 4 4-4 4M21 17H3m4-4-4 4 4 4"/>'],
    '/admin/payments' => ['Payments', '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h3"/>'],
    '/admin/cancellations' => ['Cancellations', '<path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3M8 3v5h5M8 3l5 5M16 4l5 5m0-5-5 5M7 14h7M7 17h5"/>'],
    '/admin/reports' => ['Reports', '<path d="M4 3v18h17M8 17v-5m5 5V7m5 10V4"/>'],
];
?>
<nav class="admin-nav" aria-label="Admin navigation">
    <?php foreach ($adminLinks as $path => [$label, $symbol]):
        $active = $path === '/admin' ? $currentPath === $path : ($currentPath === $path || str_starts_with($currentPath, $path . '/') || ($path === '/admin/aircraft' && str_starts_with($currentPath, '/admin/seats')) || ($path === '/admin/payments' && str_starts_with($currentPath, '/admin/tickets/'))); ?>
        <a href="<?= $path ?>"<?= $active ? ' aria-current="page"' : '' ?>><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $symbol ?></svg><?= $escape($label) ?></a>
    <?php endforeach; ?>
</nav>
