<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$expected = [
    'users', 'airports', 'aircraft', 'seats', 'flights', 'bookings',
    'passengers', 'booking_seats', 'payments', 'tickets', 'cancellations',
];

try {
    $db = Database::connection();
    $statement = $db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'');
    $found = $statement->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($expected, $found);

    if ($missing) {
        fwrite(STDERR, 'Missing tables: ' . implode(', ', $missing) . PHP_EOL);
        exit(1);
    }

    echo 'Database connection OK. All 11 Module 1 tables exist.' . PHP_EOL;
} catch (Throwable $exception) {
    error_log((string) $exception);
    fwrite(STDERR, 'Database check failed. Check MySQL, credentials, and schema import.' . PHP_EOL);
    exit(1);
}
