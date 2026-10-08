<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

// Run against the local development server using the same database config.
// HTTP requests commit registration, so only uniquely named test users are cleaned up.
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || !in_array($url['host'] ?? '', ['127.0.0.1', 'localhost'], true)
    || isset($url['user']) || isset($url['pass']) || !empty($url['path']) || isset($url['query']) || isset($url['fragment'])) {
    fwrite(STDERR, 'Use a local HTTP server URL, e.g. http://127.0.0.1:8000' . PHP_EOL);
    exit(1);
}
if (!extension_loaded('curl')) {
    fwrite(STDERR, 'The curl extension is required for authentication integration tests.' . PHP_EOL);
    exit(1);
}

$suffix = bin2hex(random_bytes(8));
$customerEmail = 'auth-customer-' . $suffix . '@example.invalid';
$adminEmail = 'auth-admin-' . $suffix . '@example.invalid';
$password = 'Auth-' . bin2hex(random_bytes(12));
$name = 'Test <script>alert(1)</script> ' . $suffix;
$db = null;
$clients = [];
$checks = 0;
$failed = false;

$assert = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($label);
    }
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
};

$client = static function () use (&$clients): CurlHandle {
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
    $clients[] = $handle;
    return $handle;
};

$request = static function (CurlHandle $handle, string $method, string $path, array $post = []) use ($base): array {
    $headers = [];
    curl_setopt($handle, CURLOPT_HTTPGET, true);
    if ($method === 'POST') {
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    curl_setopt_array($handle, [
        CURLOPT_URL => $base . $path,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException('HTTP request failed: ' . curl_error($handle));
    }
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body];
};

$token = static function (array $response): string {
    if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $matches)) {
        throw new RuntimeException('CSRF form token not found.');
    }
    return $matches[1];
};

$sessionId = static function (CurlHandle $handle): string {
    foreach (curl_getinfo($handle, CURLINFO_COOKIELIST) as $cookie) {
        $parts = explode("\t", $cookie);
        if (($parts[5] ?? '') === 'airline_session') {
            return $parts[6];
        }
    }
    throw new RuntimeException('Session cookie not found.');
};

try {
    $db = Database::connection();
    $guest = $client();
    foreach (['/.user.ini', '/.htaccess', '/%2euser.ini', '/%5c.user.ini', '/assets/%2eprivate/file', '/.env', '/config/database.php', '/storage/payment_receipts/.gitkeep'] as $privatePath) {
        foreach (['GET', 'HEAD'] as $method) {
            $response = $request($guest, $method, $privatePath);
            $assert($response['status'] === 404 && ($method !== 'HEAD' || $response['body'] === ''), $method . ' private file is not exposed: ' . $privatePath);
        }
    }
    foreach (['GET', 'HEAD'] as $method) {
        $response = $request($guest, $method, '/%00');
        $assert($response['status'] === 400 && $response['body'] === ($method === 'HEAD' ? '' : 'Invalid request path.'), $method . ' null-byte path is rejected without a stack trace');
    }
    $assert($request($guest, 'GET', '/assets/css/app.css')['status'] === 200, 'public stylesheet remains available');
    // Inject a configuration-loader failure in an isolated process; never edit local secrets.
    foreach (['GET', 'HEAD'] as $method) {
        $code = 'namespace App\\Core { final class Environment { public static function load(string $path): void { throw new \\RuntimeException("Private configuration failure"); } } } namespace {'
            . '$_SERVER["REQUEST_METHOD"] = ' . var_export($method, true) . '; ob_start(); require ' . var_export(BASE_PATH . '/public/index.php', true) . ';'
            . 'if (ob_get_level() > 1) ob_end_flush(); $body = ob_get_clean();'
            . 'exit(http_response_code() === 500 && $body === ' . var_export($method === 'HEAD' ? '' : 'The application could not complete this request.', true) . ' ? 0 : 1); }';
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Configuration test process could not start.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $assert(proc_close($process) === 0 && $output === '', $method . ' configuration failure returns safe 500 without private details');
    }
    $guest = $client(); // Inspect Set-Cookie on a fresh session, after private-path checks.
    $cookieForm = $request($guest, 'GET', '/register');
    $cookieHeader = strtolower($cookieForm['headers']['set-cookie'] ?? '');
    $assert($cookieForm['status'] === 200 && str_contains($cookieHeader, 'httponly') && str_contains($cookieHeader, 'samesite=lax'), 'session cookie uses HttpOnly and SameSite=Lax');
    foreach (['/profile' => '/login', '/admin' => '/admin/login'] as $path => $loginPath) {
        foreach (['GET', 'HEAD'] as $method) {
            $response = $request($guest, $method, $path);
            $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === $loginPath, $method . ' guest access to ' . $path . ' redirects to login');
        }
    }
    $assert($request($guest, 'GET', '/logout')['status'] === 405, 'logout requires POST');
    $form = $request($guest, 'GET', '/register');
    $csrf = $token($form);

    $fields = ['name' => $name, 'email' => $customerEmail, 'phone' => '+92 300 1234567', 'password' => $password, 'password_confirmation' => $password, 'role' => 'admin'];
    $assert($request($guest, 'POST', '/register', $fields)['status'] === 403, 'registration rejects missing CSRF');
    $fields['_token'] = $csrf;
    $invalid = $fields;
    $invalid['email'] = 'invalid-email';
    $invalid['password'] = 'short';
    $assert($request($guest, 'POST', '/register', $invalid)['status'] === 422, 'registration validates email and password');
    $invalid = $fields;
    $invalid['password_confirmation'] = 'different';
    $assert($request($guest, 'POST', '/register', $invalid)['status'] === 422, 'registration requires matching passwords');
    $response = $request($guest, 'POST', '/register', $fields);
    $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/login', 'customer registration succeeds');
    $assert(str_contains($request($guest, 'GET', '/login')['body'], 'data-auth-alert="success"'), 'registration provides a success alert');
    $assert(!str_contains($request($guest, 'GET', '/login')['body'], 'data-auth-alert="success"'), 'registration success alert is consumed once');
    $query = $db->prepare('SELECT * FROM users WHERE email = ?');
    $query->execute([$customerEmail]);
    $customer = $query->fetch();
    $assert($customer && $customer['role'] === 'customer' && $customer['status'] === 'active', 'submitted admin role is ignored');
    $assert($customer['password_hash'] !== $password && password_verify($password, $customer['password_hash']), 'password is securely hashed');
    $fields['email'] = strtoupper($customerEmail);
    $response = $request($guest, 'POST', '/register', $fields);
    $assert($response['status'] === 422 && str_contains($response['body'], 'already exists'), 'duplicate email is rejected case-insensitively');

    $form = $request($guest, 'GET', '/login');
    $csrf = $token($form);
    $before = $sessionId($guest);
    $response = $request($guest, 'POST', '/login', ['_token' => $csrf, 'email' => $customerEmail, 'password' => 'wrong-password']);
    $assert($response['status'] === 422 && str_contains($response['body'], 'Invalid email or password'), 'invalid customer login fails');
    $assert($request($guest, 'GET', '/profile')['status'] === 303, 'invalid login does not authenticate');
    $response = $request($guest, 'POST', '/login', ['_token' => $csrf, 'email' => $customerEmail, 'password' => $password]);
    $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/', 'customer login redirects to homepage');
    $assert(str_contains($request($guest, 'GET', '/')['body'], 'data-auth-alert="success"'), 'homepage shows login success alert');
    $assert(!str_contains($request($guest, 'GET', '/')['body'], 'data-auth-alert="success"'), 'login success alert is consumed once');
    $assert($before !== $sessionId($guest), 'session ID changes after customer login');
    $assert($request($guest, 'POST', '/logout', ['_token' => $csrf])['status'] === 403, 'pre-login CSRF token is invalidated');
    $profile = $request($guest, 'GET', '/profile');
    $assert($profile['status'] === 200 && str_contains($profile['body'], $customerEmail), 'profile shows the signed-in customer');
    $assert(str_contains($profile['body'], '&lt;script&gt;') && !str_contains($profile['body'], '<script>'), 'profile escapes user input');
    $assert($request($guest, 'GET', '/admin')['status'] === 403, 'customer cannot access admin area');
    $assert($request($guest, 'HEAD', '/admin')['status'] === 403, 'HEAD cannot bypass admin role guard');
    $assert($request($guest, 'POST', '/logout')['status'] === 403, 'logout rejects missing CSRF');

    $replay = $client();
    curl_setopt($replay, CURLOPT_COOKIE, 'airline_session=' . $before);
    $assert($request($replay, 'GET', '/profile')['status'] === 303, 'old session ID cannot access profile');
    $response = $request($guest, 'POST', '/logout', ['_token' => $token($profile)]);
    $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/login', 'customer logout succeeds');
    $assert(str_contains($request($guest, 'GET', '/login')['body'], 'data-auth-alert="success"'), 'logout creates a success notice in a new anonymous session');
    $assert(!str_contains($request($guest, 'GET', '/login')['body'], 'data-auth-alert="success"'), 'logout success alert is consumed once');
    $assert($request($guest, 'GET', '/profile')['status'] === 303, 'profile is protected after logout');

    $admin = $client();
    $adminForm = $request($admin, 'GET', '/admin/login');
    $adminCsrf = $token($adminForm);
    $assert($request($admin, 'POST', '/admin/login', ['_token' => $adminCsrf, 'email' => $customerEmail, 'password' => $password])['status'] === 422, 'customer credentials cannot use admin login');
    $insert = $db->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $insert->execute(['Auth test admin', $adminEmail, password_hash($password, PASSWORD_DEFAULT)]);
    $adminBefore = $sessionId($admin);
    $assert($request($admin, 'POST', '/admin/login', ['_token' => $adminCsrf, 'email' => $adminEmail, 'password' => 'wrong-password'])['status'] === 422, 'invalid admin login fails');
    $customerLoginForm = $request($guest, 'GET', '/login');
    $assert($request($guest, 'POST', '/login', ['_token' => $token($customerLoginForm), 'email' => $adminEmail, 'password' => $password])['status'] === 422, 'admin credentials cannot use customer login');
    $response = $request($admin, 'POST', '/admin/login', ['_token' => $adminCsrf, 'email' => $adminEmail, 'password' => $password]);
    $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/admin', 'admin login succeeds');
    $assert($adminBefore !== $sessionId($admin), 'session ID changes after admin login');
    $adminPage = $request($admin, 'GET', '/admin');
    $assert($adminPage['status'] === 200 && str_contains($adminPage['body'], $adminEmail), 'admin area accepts admin');
    $assert($request($admin, 'GET', '/profile')['status'] === 403, 'admin cannot access customer-only profile');
    $response = $request($admin, 'POST', '/admin/logout', ['_token' => $token($adminPage)]);
    $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/admin/login', 'admin logout succeeds');
    $assert($request($admin, 'GET', '/admin')['status'] === 303, 'admin area is protected after logout');

    $form = $request($guest, 'GET', '/login');
    $request($guest, 'POST', '/login', ['_token' => $token($form), 'email' => $customerEmail, 'password' => $password]);
    $db->prepare("UPDATE users SET status = 'inactive' WHERE email = ?")->execute([$customerEmail]);
    $assert($request($guest, 'GET', '/profile')['status'] === 303, 'deactivated users lose protected access on the next request');
    $form = $request($guest, 'GET', '/login');
    $assert($request($guest, 'POST', '/login', ['_token' => $token($form), 'email' => $customerEmail, 'password' => $password])['status'] === 422, 'inactive customer cannot log in');
    echo $checks . ' authentication checks passed.' . PHP_EOL;
} catch (Throwable $exception) {
    $failed = true;
    fwrite(STDERR, 'Authentication test failed: ' . $exception->getMessage() . PHP_EOL);
} finally {
    if ($db instanceof PDO) {
        $delete = $db->prepare('DELETE FROM users WHERE email IN (?, ?)');
        $delete->execute([$customerEmail, $adminEmail]);
        echo 'Unique authentication test users removed.' . PHP_EOL;
    }
    foreach ($clients as $handle) {
        curl_close($handle);
    }
}
exit($failed ? 1 : 0);
