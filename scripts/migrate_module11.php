<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
try {
    $db = Database::connection();
    $columnExists = static function (string $table, string $column) use ($db): bool {
        $query = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $query->execute([$table,$column]); return (bool) $query->fetchColumn();
    };
    $indexExists = static function (string $index) use ($db): bool {
        $query = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cancellations' AND INDEX_NAME = ?");
        $query->execute([$index]); return (bool) $query->fetchColumn();
    };
    $hasPreviousStatus = $columnExists('cancellations', 'previous_booking_status');
    if ((int) $db->query("SELECT COUNT(*) FROM cancellations WHERE status = 'pending'" . ($hasPreviousStatus ? ' AND previous_booking_status IS NULL' : ''))->fetchColumn()) {
        throw new RuntimeException('Legacy pending cancellation requests need manual review before upgrading; their previous booking status was not recorded. No changes made.');
    }
    if ($db->query("SELECT booking_id FROM cancellations WHERE status = 'pending' GROUP BY booking_id HAVING COUNT(*) > 1 LIMIT 1")->fetchColumn() !== false) {
        throw new RuntimeException('Resolve duplicate pending cancellation requests before upgrading. No changes made.');
    }
    $status = (string) $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'status'")->fetchColumn();
    if (!str_contains($status, "'cancellation_requested'")) $db->exec("ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','cancelled','expired','payment_submitted','cancellation_requested') NOT NULL DEFAULT 'pending'");
    if (!$columnExists('cancellations', 'previous_booking_status')) $db->exec("ALTER TABLE cancellations ADD COLUMN previous_booking_status ENUM('pending','payment_submitted','confirmed') NULL");
    if (!$columnExists('cancellations', 'active_booking_id')) $db->exec("ALTER TABLE cancellations ADD COLUMN active_booking_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'pending' THEN booking_id ELSE NULL END) STORED");
    if (!$indexExists('uq_cancellations_active_booking')) $db->exec('ALTER TABLE cancellations ADD UNIQUE KEY uq_cancellations_active_booking (active_booking_id)');
    // Preserve an ordinary booking index for the FK before dropping the old lifetime uniqueness.
    if (!$indexExists('idx_cancellations_booking')) $db->exec('ALTER TABLE cancellations ADD KEY idx_cancellations_booking (booking_id)');
    if ($indexExists('uq_cancellations_booking')) $db->exec('ALTER TABLE cancellations DROP INDEX uq_cancellations_booking');
    echo 'Module 11 migration complete (safe to run again).' . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
