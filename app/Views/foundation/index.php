<h1><?= $escape($data['title']) ?></h1>
<p>Welcome to SkyReserve. Find a scheduled flight for your next journey.</p>
<p><a class="button" href="/flights">Search flights</a></p>
<p>Create a customer account or log in to view your profile.</p>
<?php if (!$data['user']): ?>
    <div class="actions"><a class="button" href="/register">Create account</a><a href="/login">Log in</a></div>
<?php else: ?>
    <a class="button" href="<?= $data['user']['role'] === 'admin' ? '/admin' : '/profile' ?>">Open your account</a>
<?php endif; ?>
