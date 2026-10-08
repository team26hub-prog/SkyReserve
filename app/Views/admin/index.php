<span class="eyebrow">Operations overview</span>
<h1>Admin area</h1>
<p class="muted">Welcome, <?= $escape($data['user']['name']) ?>. Your airline workspace is ready.</p>
<div class="management-links">
    <a href="/admin/airports"><span class="feature-number" aria-hidden="true">⌖</span><br>Manage airports<small>Keep your destinations and airport information up to date.</small></a>
    <a href="/admin/aircraft"><span class="feature-number" aria-hidden="true">↗</span><br>Manage aircraft and seats<small>Organize your fleet, capacity, and seat configurations.</small></a>
    <a href="/admin/flights"><span class="feature-number" aria-hidden="true">⇄</span><br>Manage flights<small>Manage routes, schedules, fares, and flight statuses.</small></a>
</div>
<h2>Administrator account</h2>
<dl class="profile-details">
    <dt>Email</dt><dd><?= $escape($data['user']['email']) ?></dd>
    <dt>Account type</dt><dd><span class="badge">Admin</span></dd>
</dl>
