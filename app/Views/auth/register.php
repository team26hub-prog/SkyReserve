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
<form action="/register" method="post" class="account-form" data-register-form>
    <input type="hidden" name="_token" value="<?= $escape($csrf) ?>">
    <label for="name">Full name</label>
    <input id="name" name="name" type="text" autocomplete="name" maxlength="120" value="<?= $escape($old['name'] ?? '') ?>" aria-describedby="name-error" <?= isset($errors['name']) ? 'aria-invalid="true"' : '' ?> required>
    <small id="name-error" class="field-error" aria-live="polite"><?= $escape($errors['name'] ?? '') ?></small>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="email" maxlength="254" value="<?= $escape($old['email'] ?? '') ?>" aria-describedby="email-error" <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?> required>
    <small id="email-error" class="field-error" aria-live="polite"><?= $escape($errors['email'] ?? '') ?></small>
    <label for="phone">Phone number <span class="muted">(optional)</span></label>
    <input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="<?= $escape($old['phone'] ?? '') ?>" aria-describedby="phone-error" <?= isset($errors['phone']) ? 'aria-invalid="true"' : '' ?>>
    <small id="phone-error" class="field-error" aria-live="polite"><?= $escape($errors['phone'] ?? '') ?></small>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="password-help password-error" <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?> required>
    <small id="password-help">At least 8 characters; maximum 72 bytes (some characters use more than one byte).</small>
    <small id="password-error" class="field-error" aria-live="polite"><?= $escape($errors['password'] ?? '') ?></small>
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" aria-describedby="password_confirmation-error" <?= isset($errors['password_confirmation']) ? 'aria-invalid="true"' : '' ?> required>
    <small id="password_confirmation-error" class="field-error" aria-live="polite"><?= $escape($errors['password_confirmation'] ?? '') ?></small>
    <button type="submit">Create account</button>
</form>
<p>Already registered? <a href="/login">Log in</a></p>
