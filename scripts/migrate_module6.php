<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
try {
    $db = Database::connection();
    foreach (['bookings' => ['submission_key' => 'CHAR(64) NULL'], 'passengers' => ['full_name' => 'VARCHAR(160) NULL', 'gender' => "ENUM('male','female','other') NULL", 'phone' => 'VARCHAR(30) NULL']] as $table => $columns) {
        foreach ($columns as $column => $definition) {
            $query = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $query->execute([$table, $column]);
            if (!(int) $query->fetchColumn()) { $db->exec("ALTER TABLE $table ADD COLUMN $column $definition"); }
        }
    }
    $exists = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND INDEX_NAME = 'uq_bookings_submission'")->fetchColumn();
    if (!(int) $exists) { $db->exec('ALTER TABLE bookings ADD UNIQUE KEY uq_bookings_submission (submission_key)'); }
    $db->exec("UPDATE passengers SET full_name = TRIM(CONCAT(first_name, ' ', last_name)) WHERE full_name IS NULL");
    echo 'Module 6 migration complete (safe to run again).' . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
