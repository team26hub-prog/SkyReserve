<?php use App\Models\AdminReport; $filters = $data['result']['filters']; $type = $data['type']; ?>
<form class="report-filters" style="--filter-columns: <?= 3 + count(AdminReport::REPORTS[$type]['statuses']) ?>" method="get" action="/admin/reports/<?= $type ?>" aria-label="Report filters" data-auto-filter>
    <div><label for="start_date">Start date (UTC)</label><input type="date" id="start_date" name="start_date" min="1000-01-01" max="9999-12-31" value="<?= $escape($filters['start_date']) ?>"></div>
    <div><label for="end_date">End date (UTC)</label><input type="date" id="end_date" name="end_date" min="1000-01-01" max="9999-12-31" value="<?= $escape($filters['end_date']) ?>"></div>
    <div><label for="flight_id">Flight<?= $type === 'passengers' ? ' (required)' : '' ?></label><select id="flight_id" name="flight_id"<?= $type === 'passengers' ? ' required' : '' ?>>
        <button type="button"><selectedcontent></selectedcontent></button>
        <option value=""><?= $type === 'passengers' ? 'Choose a flight' : 'All flights' ?></option>
        <?php foreach ($data['flights'] as $flight): ?><option value="<?= (int) $flight['id'] ?>"<?= (string) $flight['id'] === $filters['flight_id'] ? ' selected' : '' ?>><?= $escape($flight['flight_number'] . ' · ' . $flight['departure_at'] . ' UTC') ?></option><?php endforeach; ?>
    </select></div>
    <?php foreach (AdminReport::REPORTS[$type]['statuses'] as $field): ?><div><label for="<?= $field ?>"><?= $escape(ucfirst(str_replace('_',' ',$field))) ?></label><select id="<?= $field ?>" name="<?= $field ?>"><button type="button"><selectedcontent></selectedcontent></button><option value="">All statuses</option>
        <?php foreach (AdminReport::statusOptions($field,$type) as $value => $label): ?><option value="<?= $value ?>"<?= $filters[$field] === $value ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?>
    </select></div><?php endforeach; ?>
    <noscript><button type="submit">Update report</button><p>Enable JavaScript for automatic filtering.</p></noscript>
</form>
<p class="muted report-date-note">Date range applies to <?= $escape(strtolower(AdminReport::REPORTS[$type]['date'])) ?> in UTC, including both boundary dates. Blank dates leave that boundary unrestricted.</p>
