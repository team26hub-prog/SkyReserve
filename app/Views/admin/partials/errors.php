<?php if (!empty($data['errors'])): ?>
    <div class="notice error" role="alert">
        <p>Please check the following:</p>
        <ul><?php foreach ($data['errors'] as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if (!empty($data['error'])): ?>
    <div class="notice error" role="alert"><?= $escape($data['error']) ?></div>
<?php endif; ?>
