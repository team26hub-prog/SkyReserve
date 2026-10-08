<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$adminLayout = ($data['user']['role'] ?? '') === 'admin' && str_starts_with($view, 'admin/');
$pageClass = match (true) {
    str_starts_with($view, 'auth/') => 'page-auth',
    $view === 'foundation/index' => 'page-home',
    $view === 'customer/flights/search' => 'page-search',
    default => 'page-standard',
};
$navCurrent = static fn (string $path): string => $currentPath === $path ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#032539">
    <title><?= $escape($data['title'] ?? 'SkyReserve') ?> | SkyReserve</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if (str_contains($view, '/tickets/')): ?><link rel="stylesheet" href="/assets/css/ticket.css"><?php endif; ?>
    <script src="/assets/js/app.js" defer></script>
</head>
<body class="<?= $pageClass ?><?= $adminLayout ? ' admin-layout' : '' ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="/" aria-label="SkyReserve home"><span class="brand-mark" aria-hidden="true">↗</span>Sky<span>Reserve</span></a>
            <button class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false">Menu <span aria-hidden="true">☰</span></button>
            <nav id="main-navigation" class="main-nav" aria-label="Main navigation">
                <a href="/"<?= $navCurrent('/') ?>>Home</a>
                <a href="/flights"<?= $navCurrent('/flights') ?>>Search flights</a>
                <?php if ($data['user']): ?>
                    <?php if ($data['user']['role'] === 'customer'): ?><a href="/bookings"<?= str_starts_with($currentPath, '/bookings') ? ' aria-current="page"' : '' ?>>My Bookings</a><?php endif; ?>
                    <a href="<?= $data['user']['role'] === 'admin' ? '/admin' : '/profile' ?>"<?= $navCurrent($data['user']['role'] === 'admin' ? '/admin' : '/profile') ?>><?= $data['user']['role'] === 'admin' ? 'Admin area' : 'Your profile' ?></a>
                    <form class="logout-form" action="<?= $data['user']['role'] === 'admin' ? '/admin/logout' : '/logout' ?>" method="post">
                        <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
                        <button class="button-secondary" type="submit">Log out</button>
                    </form>
                <?php else: ?>
                    <a href="/login"<?= $navCurrent('/login') ?>>Customer login</a>
                    <a class="button" href="/register"<?= $navCurrent('/register') ?>>Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <div class="app-shell">
        <?php if ($adminLayout): ?>
            <aside class="admin-sidebar">
                <p class="sidebar-label">Operations workspace</p>
                <?php require BASE_PATH . '/app/Views/admin/partials/navigation.php'; ?>
                <div class="sidebar-account"><span class="avatar" aria-hidden="true"><?= $escape(mb_substr($data['user']['name'], 0, 1)) ?></span><div><strong><?= $escape($data['user']['name']) ?></strong><small>Administrator</small></div></div>
            </aside>
        <?php endif; ?>
        <main id="main-content" tabindex="-1" class="app-main<?= !empty($data['wide']) ? ' wide' : '' ?>">
            <?php if ($data['flash']): ?>
                <div class="notice success" role="status"><?= $escape($data['flash']) ?></div>
            <?php endif; ?>
            <?php require $viewFile; ?>
        </main>
    </div>
    <footer class="site-footer"><div><a class="brand" href="/">Sky<span>Reserve</span></a><p>Your next journey starts here.</p></div><div><span>Single-airline travel, thoughtfully connected.</span><a href="/admin/login">Admin login</a></div></footer>
</body>
</html>
