<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($data['title'] ?? 'Airplane Ticketing System') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if (($data['user']['role'] ?? '') === 'admin'): ?>
        <script src="/assets/js/admin.js" defer></script>
    <?php endif; ?>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/">Airline Ticketing</a>
        <nav aria-label="Main navigation">
            <a href="/flights">Search flights</a>
            <?php if ($data['user']): ?>
                <a href="<?= $data['user']['role'] === 'admin' ? '/admin' : '/profile' ?>"><?= $data['user']['role'] === 'admin' ? 'Admin area' : 'Your profile' ?></a>
                <form class="logout-form" action="<?= $data['user']['role'] === 'admin' ? '/admin/logout' : '/logout' ?>" method="post">
                    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
                    <button class="button-secondary" type="submit">Log out</button>
                </form>
            <?php else: ?>
                <a href="/login">Customer login</a>
                <a href="/register">Register</a>
                <a href="/admin/login">Admin login</a>
            <?php endif; ?>
        </nav>
    </header>
    <main<?= !empty($data['wide']) ? ' class="wide"' : '' ?>>
        <?php if (($data['user']['role'] ?? '') === 'admin'): ?>
            <?php require BASE_PATH . '/app/Views/admin/partials/navigation.php'; ?>
        <?php endif; ?>
        <?php if ($data['flash']): ?>
            <div class="notice success" role="status"><?= $escape($data['flash']) ?></div>
        <?php endif; ?>
        <?php require $viewFile; ?>
    </main>
    <footer>Single-airline online ticketing system</footer>
</body>
</html>
