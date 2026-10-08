<?php $errors = $data['errors'] ?? []; $old = $data['old'] ?? []; ?>
<span class="eyebrow">Your journey starts here</span>
<h1>Create your account</h1>
<p class="muted">One account for your profile, bookings, and seat selection.</p>
<?php if ($errors): ?>
    <div class="notice error" role="alert" data-auth-alert="error">
        <p>Please check the following:</p>
        <ul><?php foreach ($errors as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<form action="/register" method="post" class="account-form">
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="name">Full name</label>
    <input id="name" name="name" type="text" autocomplete="name" maxlength="120" value="<?= $escape($old['name'] ?? '') ?>" required>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="<?= $escape($old['email'] ?? '') ?>" required>
    <label for="phone">Phone number <span class="muted">(optional)</span></label>
    <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="<?= $escape($old['phone'] ?? '') ?>">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="password-help" required>
    <small id="password-help">At least 8 characters; maximum 72 bytes (some characters use more than one byte).</small>
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
    <button type="submit">Create account</button>
</form>
<p>Already registered? <a href="/login">Log in</a></p>
