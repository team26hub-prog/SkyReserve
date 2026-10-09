<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$name = trim($argv[1] ?? '');
$email = strtolower(trim($argv[2] ?? ''));
$password = getenv('ADMIN_PASSWORD');

if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 120
    || preg_match('/[\x00-\x1F\x7F]/', $name) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || !is_string($password) || mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
    fwrite(STDERR, 'Usage: php scripts/create_admin.php "Admin name" "admin@example.com"' . PHP_EOL);
    fwrite(STDERR, 'Set ADMIN_PASSWORD in the process environment (8+ characters, at most 72 bytes).' . PHP_EOL);
    exit(1);
}

try {
    $query = Database::connection()->prepare("INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
    $query->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    echo 'Admin account created. Sign in at /login.' . PHP_EOL;
} catch (PDOException $exception) {
    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        fwrite(STDERR, 'That email already exists. Existing accounts are not modified.' . PHP_EOL);
    } else {
        error_log((string) $exception);
        fwrite(STDERR, 'Admin creation failed. Check the database configuration.' . PHP_EOL);
    }
    exit(1);
}
