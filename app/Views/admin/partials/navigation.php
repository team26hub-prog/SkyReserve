<?php
$adminLinks = [
    '/admin' => ['Overview', '◈'],
    '/admin/airports' => ['Airports', '⌖'],
    '/admin/aircraft' => ['Aircraft & seats', '↗'],
    '/admin/flights' => ['Flights', '⇄'],
    '/admin/payments' => ['Payments', '$'],
];
?>
<nav class="admin-nav" aria-label="Admin navigation">
    <?php foreach ($adminLinks as $path => [$label, $symbol]):
        $active = $path === '/admin' ? $currentPath === $path : ($currentPath === $path || str_starts_with($currentPath, $path . '/') || ($path === '/admin/aircraft' && str_starts_with($currentPath, '/admin/seats'))); ?>
        <a href="<?= $path ?>"<?= $active ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= $symbol ?></span><?= $escape($label) ?></a>
    <?php endforeach; ?>
</nav>
