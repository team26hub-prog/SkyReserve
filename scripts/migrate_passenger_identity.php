<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
try {
    $db = Database::connection();
    foreach (['cnic' => 'CHAR(13) NULL', 'passport_number' => 'VARCHAR(20) NULL'] as $column => $definition) {
        $query = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'passengers' AND COLUMN_NAME = ?");
        $query->execute([$column]);
        if (!(int) $query->fetchColumn()) { $db->exec("ALTER TABLE passengers ADD COLUMN $column $definition"); }
    }
    echo 'Passenger identity migration complete (safe to run again). Existing documents preserved.' . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
