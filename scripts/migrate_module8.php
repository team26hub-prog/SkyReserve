<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;

try {
    $db = Database::connection();
    // Do not alter historical payment records to force the new unique constraint.
    $duplicates = $db->query("SELECT booking_id FROM payments WHERE status IN ('pending','verified') GROUP BY booking_id HAVING COUNT(*) > 1 LIMIT 1")->fetchColumn();
    if ($duplicates !== false) throw new RuntimeException('Resolve existing duplicate active payments before running the migration. No records have been changed.');
    $status = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'status'")->fetchColumn();
    if (!str_contains((string) $status, "'payment_submitted'")) {
        $db->exec("ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','cancelled','expired','payment_submitted') NOT NULL DEFAULT 'pending'");
    }
    foreach ([
        'payment_date' => 'DATE NULL',
        'active_booking_id' => "BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status IN ('pending','verified') THEN booking_id ELSE NULL END) STORED",
    ] as $column => $definition) {
        $query = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = ?");
        $query->execute([$column]);
        if (!(int) $query->fetchColumn()) $db->exec("ALTER TABLE payments ADD COLUMN $column $definition");
    }
    $exists = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND INDEX_NAME = 'uq_payments_active_booking'")->fetchColumn();
    if (!(int) $exists) $db->exec('ALTER TABLE payments ADD UNIQUE KEY uq_payments_active_booking (active_booking_id)');
    echo 'Module 8 migration complete (safe to run again).' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
