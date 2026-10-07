<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || !in_array($url['host'] ?? '', ['127.0.0.1', 'localhost'], true)
    || isset($url['user']) || isset($url['pass']) || !empty($url['path']) || isset($url['query']) || isset($url['fragment'])) {
    fwrite(STDERR, 'Use a local server URL, e.g. http://127.0.0.1:8000' . PHP_EOL);
    exit(1);
}
if (!extension_loaded('curl')) {
    fwrite(STDERR, 'The curl extension is required.' . PHP_EOL);
    exit(1);
}
$suffix = bin2hex(random_bytes(5));
$number = 'T' . strtoupper($suffix);
$adminEmail = 'flights-admin-' . $suffix . '@example.invalid';
$customerEmail = 'flights-customer-' . $suffix . '@example.invalid';
$password = 'Flight-' . bin2hex(random_bytes(12));
$db = null;
$clients = $flightIds = $airportIds = $aircraftIds = [];
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
        throw new RuntimeException(curl_error($handle));
    }
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body];
};
$token = static function (array $response): string {
    if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $matches)) {
        throw new RuntimeException('CSRF token not found.');
    }
    return $matches[1];
};

try {
    $db = Database::connection();
    $insert = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    foreach ([$adminEmail => 'admin', $customerEmail => 'customer'] as $email => $role) {
        $insert->execute(['Flight test', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    $guest = $client();
    $customer = $client();
    $form = $request($customer, 'GET', '/login');
    $assert($request($customer, 'POST', '/login', ['_token' => $token($form), 'email' => $customerEmail, 'password' => $password])['status'] === 303, 'test customer signs in');
    $admin = $client();
    $form = $request($admin, 'GET', '/admin/login');
    $assert($request($admin, 'POST', '/admin/login', ['_token' => $token($form), 'email' => $adminEmail, 'password' => $password])['status'] === 303, 'test admin signs in');
    $csrf = $token($request($admin, 'GET', '/admin'));

    foreach ([['GET', ''], ['GET', '/create'], ['GET', '/edit'], ['GET', '/show'], ['POST', ''], ['POST', '/update'], ['POST', '/delete']] as [$method, $action]) {
        $path = '/admin/flights' . $action . '?id=1';
        $response = $request($guest, $method, $path);
        $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/admin/login', $method . ' guest denied: ' . $action);
        $assert($request($customer, $method, $path)['status'] === 403, $method . ' customer denied: ' . $action);
    }
    $assert($request($customer, 'HEAD', '/admin/flights/show?id=1')['status'] === 403, 'HEAD cannot bypass admin guard');
    $assert($request($admin, 'GET', '/admin/flights/delete?id=1')['status'] === 405, 'GET cannot delete a flight');
    foreach (['', '/update', '/delete'] as $action) {
        $assert($request($admin, 'POST', '/admin/flights' . $action . '?id=1')['status'] === 403, 'CSRF required: ' . $action);
        $assert($request($admin, 'POST', '/admin/flights' . $action . '?id=1', ['_token' => 'invalid'])['status'] === 403, 'invalid CSRF rejected: ' . $action);
    }

    // Unique reference fixtures are cleaned up by their own IDs in finally.
    foreach (['Origin', 'Destination'] as $name) {
        do {
            $code = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
            $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?');
            $query->execute([$code]);
        } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, ?, 'UTC')")->execute([$code, $name . ' <b>airport</b>', $name . ' city', 'Test country']);
        $airportIds[] = (int) $db->lastInsertId();
    }
    foreach (['A-', 'B-'] as $prefix) {
        $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, ?)')->execute([$prefix . $suffix, 'Test <b>aircraft</b>', 5]);
        $aircraftIds[] = (int) $db->lastInsertId();
    }
    $fields = ['_token' => $csrf, 'flight_number' => strtolower($number), 'origin_airport_id' => (string) $airportIds[0], 'destination_airport_id' => (string) $airportIds[1],
        'aircraft_id' => (string) $aircraftIds[0], 'departure_at' => '2031-01-01T10:00:30', 'arrival_at' => '2031-01-01T12:30:30', 'base_fare' => '1000.5', 'status' => 'scheduled'];
    $assert($request($admin, 'GET', '/admin/flights/create')['status'] === 200, 'create form loads airport and aircraft choices');
    $assert($request($admin, 'POST', '/admin/flights', ['_token' => $csrf])['status'] === 422, 'required fields validated');
    $cases = [
        ['flight_number', '<script>', 'invalid flight number'],
        ['origin_airport_id', $fields['destination_airport_id'], 'identical airports'],
        ['origin_airport_id', '9223372036854775807', 'missing departure airport'],
        ['destination_airport_id', '9223372036854775807', 'missing arrival airport'],
        ['aircraft_id', '9223372036854775807', 'missing aircraft'],
        ['aircraft_id', '-1', 'negative aircraft ID'],
        ['origin_airport_id', ['1'], 'array reference input'],
        ['departure_at', '2031-02-30T10:00', 'impossible date'],
        ['departure_at', '0000-01-01T10:00', 'out-of-range year'],
        ['departure_at', '2031-01-01T25:00', 'invalid hour'],
        ['arrival_at', $fields['departure_at'], 'equal arrival time'],
        ['arrival_at', '2031-01-01T09:00', 'arrival before departure'],
        ['base_fare', '0', 'zero fare'],
        ['base_fare', '-1', 'negative fare'],
        ['base_fare', '1.001', 'excess fare precision'],
        ['base_fare', '10000000000', 'fare exceeds decimal range'],
        ['base_fare', '1e3', 'exponent fare'],
        ['status', 'departed', 'unsupported status'],
        ['status', ['scheduled'], 'array status input'],
    ];
    foreach ($cases as [$key, $value, $label]) {
        $invalid = $fields;
        $invalid[$key] = $value;
        $assert($request($admin, 'POST', '/admin/flights', $invalid)['status'] === 422, 'validation rejects ' . $label);
    }

    $response = $request($admin, 'POST', '/admin/flights', $fields);
    $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/admin/flights', 'flight create succeeds');
    $find = static function (string $number, string $departure) use ($db): array {
        $query = $db->prepare('SELECT * FROM flights WHERE flight_number = ? AND departure_at = ?');
        $query->execute([$number, $departure]);
        return $query->fetch();
    };
    $flight = $find($number, '2031-01-01 10:00:30');
    $id = (int) $flight['id'];
    $flightIds[] = $id;
    $assert($flight['flight_number'] === $number && $flight['base_fare'] === '1000.50' && $flight['currency'] === 'PKR', 'flight number and exact fare stored correctly');
    $list = $request($admin, 'GET', '/admin/flights');
    $assert($list['status'] === 200 && str_contains($list['body'], $number) && str_contains($list['body'], '2031-01-01 10:00:30') && str_contains($list['body'], 'PKR 1000.50') && str_contains($list['body'], 'Scheduled'), 'list displays flight, dates, fare, and status');
    $assert(str_contains($list['body'], '&lt;b&gt;aircraft&lt;/b&gt;') && !str_contains($list['body'], 'Test <b>aircraft</b>'), 'flight list escapes joined aircraft data');
    $details = $request($admin, 'GET', '/admin/flights/show?id=' . $id);
    $assert($details['status'] === 200 && str_contains($details['body'], '&lt;b&gt;airport&lt;/b&gt;') && str_contains($details['body'], '1000.50'), 'details show escaped route and fare');
    $edit = $request($admin, 'GET', '/admin/flights/edit?id=' . $id);
    $assert($edit['status'] === 200 && str_contains($edit['body'], '2031-01-01T10:00:30'), 'edit form preserves date/time seconds');
    $assert($request($admin, 'POST', '/admin/flights', $fields)['status'] === 422, 'duplicate number and departure rejected');
    $recurring = $fields;
    $recurring['departure_at'] = '2031-01-02T10:00:30';
    $recurring['arrival_at'] = '2031-01-02T12:30:30';
    $assert($request($admin, 'POST', '/admin/flights', $recurring)['status'] === 303, 'same flight number allowed at another departure');
    $otherId = (int) $find($number, '2031-01-02 10:00:30')['id'];
    $flightIds[] = $otherId;
    $assert($request($admin, 'POST', '/admin/flights/update?id=' . $id, $recurring)['status'] === 422, 'duplicate departure rejected on update');
    $assert($find($number, '2031-01-01 10:00:30')['base_fare'] === '1000.50', 'invalid update preserves existing flight');

    $updated = $fields;
    $updated['flight_number'] = 'U' . strtoupper($suffix);
    $updated['origin_airport_id'] = (string) $airportIds[1];
    $updated['destination_airport_id'] = (string) $airportIds[0];
    $updated['aircraft_id'] = (string) $aircraftIds[1];
    $updated['departure_at'] = '2031-01-03T10:01';
    $updated['arrival_at'] = '2031-01-03T12:31';
    $updated['base_fare'] = '1001.23';
    foreach (['scheduled', 'delayed', 'cancelled', 'completed'] as $status) {
        $updated['status'] = $status;
        $assert($request($admin, 'POST', '/admin/flights/update?id=' . $id, $updated)['status'] === 303, 'flight update accepts ' . $status);
    }
    $saved = $find($updated['flight_number'], '2031-01-03 10:01:00');
    $assert($saved['origin_airport_id'] == $airportIds[1] && $saved['destination_airport_id'] == $airportIds[0]
        && $saved['aircraft_id'] == $aircraftIds[1] && $saved['base_fare'] === '1001.23' && $saved['status'] === 'completed', 'updated route, aircraft, dates, fare, and status persist');
    $maximum = $updated;
    $maximum['base_fare'] = '9999999999.99';
    $assert($request($admin, 'POST', '/admin/flights/update?id=' . $id, $maximum)['status'] === 303, 'maximum supported fare accepted');
    $assert($find($updated['flight_number'], '2031-01-03 10:01:00')['base_fare'] === '9999999999.99', 'large fare retains exact cents');
    $minimum = $updated;
    $minimum['base_fare'] = '0.01';
    $assert($request($admin, 'POST', '/admin/flights/update?id=' . $id, $minimum)['status'] === 303, 'minimum positive fare accepted');
    $assert($request($admin, 'POST', '/admin/flights/delete?id=' . $id, ['_token' => 'invalid'])['status'] === 403, 'invalid CSRF cannot delete an existing flight');
    $assert($request($admin, 'GET', '/admin/flights/show?id=' . $id)['status'] === 200, 'CSRF failure leaves flight intact');
    $assert($request($admin, 'POST', '/admin/airports/delete?id=' . $airportIds[0], ['_token' => $csrf])['status'] === 409, 'flight protects its referenced airport from deletion');
    $assert($request($admin, 'POST', '/admin/aircraft/delete?id=' . $aircraftIds[1], ['_token' => $csrf])['status'] === 409, 'flight protects its referenced aircraft from deletion');
    $assert($request($admin, 'GET', '/admin/flights/show?id[]=1')['status'] === 404, 'malformed flight ID rejected');
    $assert($request($admin, 'GET', '/admin/flights/edit?id=9223372036854775807')['status'] === 404, 'missing flight rejected');
    $assert($request($admin, 'POST', '/admin/flights/update?id=9223372036854775807', $updated)['status'] === 404, 'missing flight cannot be updated');
    foreach ($flightIds as $flightId) {
        $assert($request($admin, 'POST', '/admin/flights/delete?id=' . $flightId, ['_token' => $csrf])['status'] === 303, 'flight delete succeeds');
        $assert($request($admin, 'GET', '/admin/flights/show?id=' . $flightId)['status'] === 404, 'deleted flight is absent');
    }
    $assert($request($admin, 'POST', '/admin/flights/delete?id=' . $id, ['_token' => $csrf])['status'] === 404, 'deleting missing flight returns 404');
    echo $checks . ' Module 4 checks passed.' . PHP_EOL;
} catch (Throwable $exception) {
    $failed = true;
    fwrite(STDERR, 'Flight test failed: ' . $exception->getMessage() . PHP_EOL);
} finally {
    if ($db instanceof PDO) {
        foreach (['flights' => $flightIds, 'aircraft' => $aircraftIds, 'airports' => $airportIds] as $table => $ids) {
            if ($ids) {
                $slots = implode(',', array_fill(0, count($ids), '?'));
                $db->prepare('DELETE FROM ' . $table . ' WHERE id IN (' . $slots . ')')->execute($ids);
            }
        }
        $db->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([$adminEmail, $customerEmail]);
        echo 'Unique Module 4 test records removed.' . PHP_EOL;
    }
    foreach ($clients as $handle) {
        curl_close($handle);
    }
}
exit($failed ? 1 : 0);
