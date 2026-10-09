<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$adminLayout = ($data['user']['role'] ?? '') === 'admin' && str_starts_with($view, 'admin/');
$customerLayout = ($data['user']['role'] ?? '') === 'customer';
$adminLinks = ($data['user']['role'] ?? '') === 'admin' ? require BASE_PATH . '/app/Views/admin/partials/links.php' : [];
$adminInitials = '';
if ($adminLayout || $customerLayout) {
    $nameParts = preg_split('/\s+/u', trim($data['user']['name']), -1, PREG_SPLIT_NO_EMPTY);
    foreach (array_slice($nameParts, 0, 2) as $namePart) {
        $adminInitials .= mb_substr($namePart, 0, 1);
    }
    $adminInitials = mb_strtoupper($adminInitials);
}
$pageClass = match (true) {
    str_starts_with($view, 'auth/') => 'page-auth',
    $view === 'foundation/index' => 'page-home',
    $view === 'customer/flights/search' => 'page-search',
    default => 'page-standard',
};
$navCurrent = static fn (string $path): string => $currentPath === $path ? ' aria-current="page"' : '';
?>
<!doctype html>
<html lang="en" id="page-top">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#032539">
    <title><?= $escape($data['title'] ?? 'SkyReserve') ?> | SkyReserve</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if (str_contains($view, '/tickets/')): ?><link rel="stylesheet" href="/assets/css/ticket.css"><?php endif; ?>
    <script src="/assets/js/app.js" defer></script>
</head>
<body class="<?= $pageClass ?><?= $adminLayout ? ' admin-layout' : '' ?><?= $customerLayout ? ' customer-layout' : '' ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="/" aria-label="SkyReserve home"><span class="brand-logo" aria-hidden="true"><img src="/assets/images/skyreserve-globe-logo.png" alt="" width="1254" height="1254" decoding="async"></span>Sky<span>Reserve</span></a>
            <button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="<?= $customerLayout ? 'customer-sidebar' : ($adminLayout ? 'admin-sidebar' : 'main-navigation') ?>" aria-expanded="false"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <nav id="main-navigation" class="main-nav" aria-label="Main navigation">
                <?php if (!$adminLayout && !$customerLayout): ?>
                <a href="/"<?= $navCurrent('/') ?>>Home</a>
                <a href="/flights"<?= $navCurrent('/flights') ?>>Search flights</a>
                <?php endif; ?>
                <?php if ($data['user']): ?>
                    <?php if ($adminLayout || $customerLayout): ?>
                    <div class="header-account" aria-label="<?= $escape($data['user']['name']) ?>"><span class="avatar" aria-hidden="true"><?= $escape($adminInitials) ?></span><span class="header-account-name"><?= $escape($data['user']['name']) ?></span></div>
                    <?php else: ?>
                    <a href="<?= $data['user']['role'] === 'admin' ? '/admin' : '/profile' ?>"<?= $navCurrent($data['user']['role'] === 'admin' ? '/admin' : '/profile') ?>><?= $data['user']['role'] === 'admin' ? 'Admin panel' : 'Your profile' ?></a>
                    <?php endif; ?>
                    <?php if (!$customerLayout): ?>
                    <form class="logout-form" action="<?= $data['user']['role'] === 'admin' ? '/admin/logout' : '/logout' ?>" method="post">
                        <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
                        <button class="button-secondary" type="submit">Log out</button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/login"<?= $navCurrent('/login') ?>>Log in</a>
                    <a class="button" href="/register"<?= $navCurrent('/register') ?>>Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <div class="app-shell">
        <?php if ($customerLayout): ?>
            <aside id="customer-sidebar" class="customer-sidebar" aria-label="Your travel workspace" tabindex="-1">
                <div class="sidebar-heading"><p class="sidebar-label">Your travel workspace</p><button class="sidebar-close" type="button" aria-label="Close navigation"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
                <?php require BASE_PATH . '/app/Views/customer/partials/navigation.php'; ?>
                <form class="logout-form sidebar-logout" action="/logout" method="post">
                    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
                    <button class="button-secondary" type="submit"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4m7-14 5 7-5 7M8 12h13"/></svg>Log out</button>
                </form>
            </aside>
        <?php endif; ?>
        <?php if ($adminLayout): ?>
            <aside id="admin-sidebar" class="admin-sidebar" aria-label="Operations workspace" tabindex="-1">
                <div class="sidebar-heading"><p class="sidebar-label">Operations workspace</p><button class="sidebar-close" type="button" aria-label="Close navigation"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
                <?php require BASE_PATH . '/app/Views/admin/partials/navigation.php'; ?>
                <div class="sidebar-account"><span class="avatar" aria-hidden="true"><?= $escape(mb_substr($data['user']['name'], 0, 1)) ?></span><div><strong><?= $escape($data['user']['name']) ?></strong><small>Administrator</small></div></div>
                <form class="logout-form sidebar-logout admin-mobile-logout" action="/admin/logout" method="post"><input type="hidden" name="_token" value="<?= $escape($csrf) ?>"><button class="button-secondary" type="submit">Log out</button></form>
            </aside>
        <?php endif; ?>
        <main id="main-content" tabindex="-1" class="app-main<?= !empty($data['wide']) ? ' wide' : '' ?>">
            <?php require BASE_PATH . '/app/Views/layouts/back-navigation.php'; ?>
            <?php if ($data['flash']): ?>
                <div class="notice success" role="status" data-toast="success"<?= !empty($data['authAlert']) ? ' data-auth-alert="success"' : '' ?>><?= $escape($data['flash']) ?></div>
            <?php endif; ?>
            <?php require $viewFile; ?>
        </main>
    </div>
    <?php if (!$adminLayout && !$customerLayout): ?>
    <footer class="site-footer">
        <div><a class="brand" href="/">Sky<span>Reserve</span></a><p>Your next journey starts here.</p><small>Single-airline travel, thoughtfully connected.</small></div>
        <nav class="footer-links" aria-label="Footer navigation">
            <a href="/flights">Search flights</a>
            <?php if (($data['user']['role'] ?? '') === 'customer'): ?><a href="/bookings">My Bookings</a><a href="/profile">Your profile</a>
            <?php elseif (($data['user']['role'] ?? '') === 'admin'): ?><a href="/admin">Admin dashboard</a><a href="/admin/reports">Reports</a>
            <?php else: ?><a href="/login">Log in</a><a href="/register">Create account</a><?php endif; ?>
        </nav>
    </footer>
    <?php endif; ?>
    <a class="back-to-top" href="#page-top" aria-label="Back to top" title="Back to top"><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-7 7 7-7 7 7"/></svg></a>
    <?php require BASE_PATH . '/app/Views/layouts/mobile-navigation.php'; ?>
    <?php require BASE_PATH . '/app/Views/layouts/confirmation.php'; ?>
    <div class="toast-region" role="region" aria-label="Notifications"></div>
</body>
</html>
