<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

try {
    $db = Database::connection();
    if ((int) $db->query("SELECT COUNT(*) FROM seats WHERE cabin_class = 'first'")->fetchColumn() > 0) {
        throw new RuntimeException('First-class seats exist. Review and reclassify them before running this migration; no changes were made.');
    }
    $sql = file_get_contents(BASE_PATH . '/database/migrations/003_aircraft_capacity_and_seat_classes.sql');
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    $column = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aircraft' AND COLUMN_NAME = 'total_capacity'")->fetchColumn();
    if (!(int) $column) {
        $db->exec($statements[0]);
    }
    $db->exec($statements[1]);
    $check = $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'aircraft' AND CONSTRAINT_NAME = 'chk_aircraft_capacity'")->fetchColumn();
    if (!(int) $check) {
        $db->exec($statements[2]);
    }
    $type = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seats' AND COLUMN_NAME = 'cabin_class'")->fetchColumn();
    if ($type !== "enum('economy','business')") {
        $db->exec($statements[3]);
    }
    echo 'Module 3 database migration complete (safe to run again).' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
