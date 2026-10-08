<?php

declare(strict_types=1);

// Router for the PHP development server. Serve static assets only inside public.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$decodedPath = is_string($path) ? rawurldecode($path) : '';
if (!is_string($path) || str_contains($decodedPath, "\0")) {
    http_response_code(400);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo 'Invalid request path.';
    return true;
}
// Configuration files and hidden directories are never public assets.
// Windows also treats backslashes as directory separators.
if (preg_match('~(?:^|/)[.]~', str_replace('\\', '/', $decodedPath))) {
    http_response_code(404);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo 'Page not found.';
    return true;
}
$file = realpath(__DIR__ . '/' . $decodedPath);
$publicRoot = realpath(__DIR__) . DIRECTORY_SEPARATOR;

if ($file !== false && str_starts_with($file, $publicRoot) && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php') {
    return false;
}

require __DIR__ . '/index.php';
