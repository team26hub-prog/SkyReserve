<span class="eyebrow">Your SkyReserve account</span>
<div class="page-heading"><h1>Your profile</h1><span class="badge">Customer</span></div>
<p class="muted">Welcome, <?= $escape($data['user']['name']) ?>. Ready for your next journey?</p>
<dl class="profile-details">
    <dt>Full name</dt><dd><?= $escape($data['user']['name']) ?></dd>
    <dt>Email</dt><dd><?= $escape($data['user']['email']) ?></dd>
    <dt>Phone</dt><dd><?= $escape($data['user']['phone'] ?? 'Not provided') ?></dd>
    <dt>Account type</dt><dd>Customer</dd>
    <dt>Member since (UTC)</dt><dd><?= $escape($data['user']['created_at']) ?></dd>
</dl>
<a class="button" href="/flights">Find your next flight →</a>
