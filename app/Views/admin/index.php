<h1>Admin area</h1>
<p>Welcome, <?= $escape($data['user']['name']) ?>.</p>
<p>You are signed in as an administrator.</p>
<div class="management-links">
    <a href="/admin/airports">Manage airports</a>
    <a href="/admin/aircraft">Manage aircraft and seats</a>
    <a href="/admin/flights">Manage flights</a>
</div>
<dl class="profile-details">
    <dt>Email</dt><dd><?= $escape($data['user']['email']) ?></dd>
    <dt>Account type</dt><dd>Admin</dd>
</dl>
