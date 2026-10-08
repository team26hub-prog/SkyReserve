<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

// Suppress every HEAD response body, including bootstrap failures.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    ob_start(static fn (string $body): string => '');
}

try {
    require dirname(__DIR__) . '/bootstrap.php';
    App\Core\Session::start();
    $routes = require BASE_PATH . '/routes/web.php';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $lookupMethod = $method === 'HEAD' ? 'GET' : $method;
    $route = $routes[$lookupMethod][$path] ?? null;

    if ($route === null) {
        $allowed = [];
        foreach ($routes as $routeMethod => $paths) {
            if (isset($paths[$path])) {
                $allowed[] = $routeMethod;
                if ($routeMethod === 'GET') {
                    $allowed[] = 'HEAD';
                }
            }
        }

        http_response_code($allowed ? 405 : 404);
        if ($allowed) {
            header('Allow: ' . implode(', ', $allowed));
        }
        if ($method !== 'HEAD') {
            echo $allowed ? 'Method not allowed.' : 'Page not found.';
        }
        exit;
    }

    // HEAD executes the same access checks as GET.
    [$controller, $action] = $route;
    (new $controller())->$action();
} catch (Throwable $exception) {
    error_log((string) $exception);
    http_response_code(500);
    echo 'The application could not complete this request.';
}
