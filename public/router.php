<?php

declare(strict_types=1);

// Router for the PHP development server. Serve static assets only inside public.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = is_string($path) ? realpath(__DIR__ . '/' . rawurldecode($path)) : false;
$publicRoot = realpath(__DIR__) . DIRECTORY_SEPARATOR;

if ($file !== false && str_starts_with($file, $publicRoot) && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}

require __DIR__ . '/index.php';
