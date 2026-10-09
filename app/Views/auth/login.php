<span class="eyebrow">Welcome back</span>
<h1><?= $escape($data['title']) ?></h1>
<p class="muted">Sign in to your SkyReserve account.</p>
<?php if (isset($data['error'])): ?>
    <div class="notice error" role="alert" data-auth-alert="error"><?= $escape($data['error']) ?></div>
<?php endif; ?>
<form action="/login" method="post" class="account-form">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="username" maxlength="254" value="<?= $escape($data['email'] ?? '') ?>" required>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" maxlength="72" required>
    <button type="submit">Log in</button>
</form>
<p>New here? <a href="/register">Create an account</a></p>
