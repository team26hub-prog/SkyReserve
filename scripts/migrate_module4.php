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
    if ((int) $db->query("SELECT COUNT(*) FROM flights WHERE base_fare <= 0 OR status = 'departed'")->fetchColumn() > 0) {
        throw new RuntimeException('Review flights with nonpositive fares or departed status before upgrading. No changes were made.');
    }
    $db->exec(file_get_contents(BASE_PATH . '/database/migrations/004_flight_validation.sql'));
    echo 'Module 4 database migration complete (safe to run again).' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
