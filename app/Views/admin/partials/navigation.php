<nav class="admin-nav" aria-label="Admin navigation">
    <?php foreach ($adminLinks as $path => [$label, $symbol]):
        $active = $path === '/admin' ? $currentPath === $path : ($currentPath === $path || str_starts_with($currentPath, $path . '/') || ($path === '/admin/aircraft' && str_starts_with($currentPath, '/admin/seats')) || ($path === '/admin/payments' && str_starts_with($currentPath, '/admin/tickets/'))); ?>
        <a href="<?= $path ?>"<?= $active ? ' aria-current="page"' : '' ?>><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $symbol ?></svg><?= $escape($label) ?></a>
    <?php endforeach; ?>
</nav>
