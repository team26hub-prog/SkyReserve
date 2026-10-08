<span class="eyebrow">Account access</span>
<h1><?= $escape($data['title']) ?></h1>
<p><?= $escape($data['message'] ?? 'Your account does not have permission to access this page.') ?></p>
<a class="button" href="/">Return home</a>
