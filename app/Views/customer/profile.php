<h1>Your profile</h1>
<p>Welcome, <?= $escape($data['user']['name']) ?>.</p>
<dl class="profile-details">
    <dt>Full name</dt><dd><?= $escape($data['user']['name']) ?></dd>
    <dt>Email</dt><dd><?= $escape($data['user']['email']) ?></dd>
    <dt>Phone</dt><dd><?= $escape($data['user']['phone'] ?? 'Not provided') ?></dd>
    <dt>Account type</dt><dd>Customer</dd>
    <dt>Member since (UTC)</dt><dd><?= $escape($data['user']['created_at']) ?></dd>
</dl>
