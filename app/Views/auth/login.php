<?php $isAdmin = $data['role'] === 'admin'; ?>
<h1><?= $escape($data['title']) ?></h1>
<p class="muted"><?= $isAdmin ? 'Sign in with your administrator account.' : 'Sign in to view your customer profile.' ?></p>
<?php if (isset($data['error'])): ?>
    <div class="notice error" role="alert"><?= $escape($data['error']) ?></div>
<?php endif; ?>
<form action="<?= $isAdmin ? '/admin/login' : '/login' ?>" method="post" class="account-form">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="username" maxlength="254" value="<?= $escape($data['email'] ?? '') ?>" required>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
    <button type="submit">Log in</button>
</form>
<?php if (!$isAdmin): ?>
    <p>New here? <a href="/register">Create a customer account</a></p>
<?php endif; ?>
