<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
try {
    $db = Database::connection();
    $exists = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND INDEX_NAME = 'idx_bookings_created'")->fetchColumn();
    if (!(int) $exists) $db->exec('ALTER TABLE bookings ADD INDEX idx_bookings_created (created_at)');
    echo 'Report analytics index ready (safe to run again).' . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
