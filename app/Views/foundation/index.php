<h1><?= $escape($data['title']) ?></h1>
<p>Welcome to your airline account portal.</p>
<p>Create a customer account or log in to view your profile.</p>
<?php if (!$data['user']): ?>
    <div class="actions"><a class="button" href="/register">Create account</a><a href="/login">Log in</a></div>
<?php else: ?>
    <a class="button" href="<?= $data['user']['role'] === 'admin' ? '/admin' : '/profile' ?>">Open your account</a>
<?php endif; ?>
