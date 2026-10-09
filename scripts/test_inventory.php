<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\Aircraft;
use App\Models\Seat;

// Internal child-process mode exercises simultaneous model writes to a test aircraft.
if (($argv[1] ?? '') === '--capacity-worker') {
    try {
        $id = (int) ($argv[2] ?? 0);
        $aircraft = (new Aircraft())->find($id);
        if (!$aircraft || !str_starts_with($aircraft['registration_number'], 'R-')) {
            throw new RuntimeException('Capacity test aircraft not found.');
        }
        echo 'READY' . PHP_EOL;
        flush();
        try {
            (new Seat())->save($id, ['seat_number' => $argv[3], 'cabin_class' => 'economy']);
            echo 'CREATED' . PHP_EOL;
        } catch (DomainException) {
            echo 'CAPACITY' . PHP_EOL;
        }
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

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

$suffix = bin2hex(random_bytes(8));
$adminEmail = 'inventory-admin-' . $suffix . '@example.invalid';
$customerEmail = 'inventory-customer-' . $suffix . '@example.invalid';
$password = 'Inventory-' . bin2hex(random_bytes(12));
$db = null;
$clients = $workers = $airportIds = $aircraftIds = [];
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
        $insert->execute(['Inventory test', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    $guest = $client();
    $customer = $client();
    $form = $request($customer, 'GET', '/login');
    $assert($request($customer, 'POST', '/login', ['_token' => $token($form), 'email' => $customerEmail, 'password' => $password])['status'] === 303, 'test customer signs in');
    $admin = $client();
    $form = $request($admin, 'GET', '/login');
    $assert($request($admin, 'POST', '/login', ['_token' => $token($form), 'email' => $adminEmail, 'password' => $password])['status'] === 303, 'test admin signs in');
    $csrf = $token($request($admin, 'GET', '/admin'));

    // Every Module 3 endpoint is checked before it can look up a record or mutate it.
    foreach (['airports', 'aircraft', 'seats'] as $resource) {
        foreach (['' => 'GET', '/create' => 'GET', '/edit' => 'GET', '/update' => 'POST', '/delete' => 'POST', '?store' => 'POST'] as $suffixPath => $method) {
            $path = '/admin/' . $resource . ($suffixPath === '?store' ? '' : $suffixPath) . '?id=1&aircraft_id=1';
            $response = $request($guest, $method, $path);
            $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/login', $method . ' guest denied: ' . $resource . ' ' . $suffixPath);
            $assert($request($customer, $method, $path)['status'] === 403, $method . ' customer denied: ' . $resource . ' ' . $suffixPath);
        }
        foreach (['', '/update', '/delete'] as $action) {
            $assert($request($admin, 'POST', '/admin/' . $resource . $action . '?id=1&aircraft_id=1')['status'] === 403, 'CSRF required: ' . $resource . $action);
        }
    }
    $assert($request($customer, 'HEAD', '/admin/aircraft')['status'] === 403, 'HEAD remains protected');
    $assert($request($admin, 'GET', '/admin/airports/delete?id=1')['status'] === 405, 'GET cannot delete records');

    $freeCode = static function () use ($db): string {
        do {
            $code = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
            $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?');
            $query->execute([$code]);
        } while ($query->fetchColumn() !== false);
        return $code;
    };
    $code = $freeCode();
    $airport = ['_token' => $csrf, 'iata_code' => strtolower($code), 'name' => 'Test <b>airport</b> ' . $suffix, 'city' => 'Test city', 'country' => 'Test country'];
    $assert($request($admin, 'POST', '/admin/airports', ['_token' => $csrf])['status'] === 422, 'airport required fields validated');
    $invalid = $airport;
    $invalid['iata_code'] = 'A1';
    $assert($request($admin, 'POST', '/admin/airports', $invalid)['status'] === 422, 'airport code must be three letters');
    $assert($request($admin, 'POST', '/admin/airports', $airport)['status'] === 303, 'airport create succeeds');
    $query = $db->prepare('SELECT * FROM airports WHERE iata_code = ?');
    $query->execute([$code]);
    $airportRow = $query->fetch();
    $airportId = (int) $airportRow['id'];
    $airportIds[] = $airportId;
    $assert($airportRow['iata_code'] === $code && $airportRow['timezone'] === 'UTC', 'airport normalized and required timezone supplied');
    $list = $request($admin, 'GET', '/admin/airports');
    $assert($list['status'] === 200 && str_contains($list['body'], $code) && str_contains($list['body'], '&lt;b&gt;airport&lt;/b&gt;'), 'airport list renders escaped data');
    $assert($request($admin, 'POST', '/admin/airports', $airport)['status'] === 422, 'duplicate airport code rejected');
    $assert($request($admin, 'GET', '/admin/airports/edit?id=' . $airportId)['status'] === 200, 'airport edit form available');
    $airport['city'] = 'Updated city';
    $assert($request($admin, 'POST', '/admin/airports/update?id=' . $airportId, $airport)['status'] === 303, 'airport update succeeds');
    $query->execute([$code]);
    $assert($query->fetch()['city'] === 'Updated city', 'airport update persisted');
    $assert($request($admin, 'GET', '/admin/airports/edit?id[]=1')['status'] === 404, 'malformed record ID rejected');

    $aircraft = ['_token' => $csrf, 'model' => 'Test aircraft ' . $suffix, 'registration_number' => 'T-' . $suffix, 'total_capacity' => '2'];
    $assert($request($admin, 'POST', '/admin/aircraft', ['_token' => $csrf])['status'] === 422, 'aircraft required fields validated');
    foreach (['0', '-1', '1.5', '4294967296'] as $capacity) {
        $invalid = $aircraft;
        $invalid['total_capacity'] = $capacity;
        $assert($request($admin, 'POST', '/admin/aircraft', $invalid)['status'] === 422, 'invalid capacity rejected: ' . $capacity);
    }
    $assert($request($admin, 'POST', '/admin/aircraft', $aircraft)['status'] === 303, 'aircraft create succeeds');
    $findAircraft = static function (string $registration) use ($db): array {
        $query = $db->prepare('SELECT * FROM aircraft WHERE registration_number = ?');
        $query->execute([$registration]);
        return $query->fetch();
    };
    $aircraftId = (int) $findAircraft($aircraft['registration_number'])['id'];
    $aircraftIds[] = $aircraftId;
    $list = $request($admin, 'GET', '/admin/aircraft');
    $assert($list['status'] === 200 && str_contains($list['body'], strtoupper($aircraft['registration_number'])), 'aircraft list succeeds');
    $duplicate = $aircraft;
    $duplicate['registration_number'] = strtolower($aircraft['registration_number']);
    $assert($request($admin, 'POST', '/admin/aircraft', $duplicate)['status'] === 422, 'duplicate aircraft registration rejected case-insensitively');
    $assert($request($admin, 'GET', '/admin/aircraft/edit?id=' . $aircraftId)['status'] === 200, 'aircraft edit form available');
    $aircraft['model'] = 'Updated aircraft ' . $suffix;
    $assert($request($admin, 'POST', '/admin/aircraft/update?id=' . $aircraftId, $aircraft)['status'] === 303, 'aircraft update succeeds');
    $assert($findAircraft($aircraft['registration_number'])['model'] === $aircraft['model'], 'aircraft update persisted');
    $other = $aircraft;
    $other['registration_number'] = 'U-' . $suffix;
    $other['total_capacity'] = '1';
    $assert($request($admin, 'POST', '/admin/aircraft', $other)['status'] === 303, 'second aircraft created');
    $otherId = (int) $findAircraft($other['registration_number'])['id'];
    $aircraftIds[] = $otherId;
    $duplicate = $aircraft;
    $duplicate['registration_number'] = $other['registration_number'];
    $assert($request($admin, 'POST', '/admin/aircraft/update?id=' . $aircraftId, $duplicate)['status'] === 422, 'duplicate registration rejected on edit');

    $seatPath = '/admin/seats?aircraft_id=' . $aircraftId;
    $seat = ['_token' => $csrf, 'seat_number' => '1a', 'cabin_class' => 'economy'];
    $assert($request($admin, 'GET', $seatPath)['status'] === 200, 'per-aircraft seat list succeeds');
    $assert($request($admin, 'GET', '/admin/seats/create?aircraft_id=' . $aircraftId)['status'] === 200, 'seat create form available');
    $assert($request($admin, 'POST', $seatPath, ['_token' => $csrf])['status'] === 422, 'seat required fields validated');
    $invalid = $seat;
    $invalid['cabin_class'] = 'first';
    $assert($request($admin, 'POST', $seatPath, $invalid)['status'] === 422, 'unsupported seat class rejected');
    $assert($request($admin, 'POST', $seatPath, $seat)['status'] === 303, 'economy seat create succeeds');
    $findSeat = static function (int $parent, string $number) use ($db): array {
        $query = $db->prepare('SELECT * FROM seats WHERE aircraft_id = ? AND seat_number = ?');
        $query->execute([$parent, $number]);
        return $query->fetch();
    };
    $seatId = (int) $findSeat($aircraftId, '1A')['id'];
    $assert($request($admin, 'POST', $seatPath, $seat)['status'] === 422, 'duplicate seat number rejected');
    $seat2 = ['_token' => $csrf, 'seat_number' => '1B', 'cabin_class' => 'business'];
    $assert($request($admin, 'POST', $seatPath, $seat2)['status'] === 303, 'business seat create succeeds');
    $seat2Id = (int) $findSeat($aircraftId, '1B')['id'];
    $extra = $seat2;
    $extra['seat_number'] = '1C';
    $response = $request($admin, 'POST', $seatPath, $extra);
    $assert($response['status'] === 422 && str_contains($response['body'], 'at capacity'), 'seat count cannot exceed aircraft capacity');
    $assert($request($admin, 'POST', '/admin/seats?aircraft_id=' . $otherId, $seat)['status'] === 303, 'same seat number allowed on another aircraft');
    $otherSeatId = (int) $findSeat($otherId, '1A')['id'];
    $assert($request($admin, 'GET', '/admin/seats/edit?aircraft_id=' . $aircraftId . '&id=' . $seatId)['status'] === 200, 'seat edit form available');
    $assert($request($admin, 'POST', '/admin/seats/update?aircraft_id=' . $aircraftId . '&id=' . $seat2Id, $seat)['status'] === 422, 'duplicate seat number rejected on edit');
    $seat2['seat_number'] = '2C';
    $seat2['cabin_class'] = 'economy';
    $assert($request($admin, 'POST', '/admin/seats/update?aircraft_id=' . $aircraftId . '&id=' . $seat2Id, $seat2)['status'] === 303, 'seat number and class update succeeds at capacity');
    $assert($findSeat($aircraftId, '2C')['cabin_class'] === 'economy', 'seat update persisted');
    foreach (['GET' => '/edit', 'POST' => '/update'] as $method => $action) {
        $assert($request($admin, $method, '/admin/seats' . $action . '?aircraft_id=' . $otherId . '&id=' . $seatId, $seat)['status'] === 404, 'seat parent mismatch rejected: ' . $method);
    }
    $assert($request($admin, 'POST', '/admin/seats/delete?aircraft_id=' . $otherId . '&id=' . $seatId, ['_token' => $csrf])['status'] === 404, 'cannot delete seat through another aircraft');
    $smaller = $aircraft;
    $smaller['total_capacity'] = '1';
    $assert($request($admin, 'POST', '/admin/aircraft/update?id=' . $aircraftId, $smaller)['status'] === 422, 'cannot shrink capacity below seat count');
    $assert((int) $findAircraft($aircraft['registration_number'])['total_capacity'] === 2, 'failed capacity update leaves record unchanged');
    $assert($request($admin, 'POST', '/admin/aircraft/delete?id=' . $aircraftId, ['_token' => $csrf])['status'] === 409, 'aircraft with seats cannot be deleted');
    $assert($request($admin, 'GET', '/admin/seats?aircraft_id=9223372036854775807')['status'] === 404, 'missing aircraft returns 404');

    foreach ([[$aircraftId, $seatId], [$aircraftId, $seat2Id], [$otherId, $otherSeatId]] as [$parent, $id]) {
        $assert($request($admin, 'POST', '/admin/seats/delete?aircraft_id=' . $parent . '&id=' . $id, ['_token' => $csrf])['status'] === 303, 'seat delete succeeds: ' . $id);
    }
    foreach ([$aircraftId, $otherId] as $id) {
        $assert($request($admin, 'POST', '/admin/aircraft/delete?id=' . $id, ['_token' => $csrf])['status'] === 303, 'aircraft delete succeeds after seats removed');
        $assert($request($admin, 'GET', '/admin/aircraft/edit?id=' . $id)['status'] === 404, 'deleted aircraft is absent');
    }
    $assert($request($admin, 'POST', '/admin/airports/delete?id=' . $airportId, ['_token' => $csrf])['status'] === 303, 'airport delete succeeds');
    $assert($request($admin, 'GET', '/admin/airports/edit?id=' . $airportId)['status'] === 404, 'deleted airport is absent');

    // Hold the parent lock until both workers are ready, then let them compete for one slot.
    $race = $aircraft;
    $race['registration_number'] = 'R-' . $suffix;
    $race['total_capacity'] = '1';
    $assert($request($admin, 'POST', '/admin/aircraft', $race)['status'] === 303, 'capacity test aircraft created');
    $raceId = (int) $findAircraft($race['registration_number'])['id'];
    $aircraftIds[] = $raceId;
    $db->beginTransaction();
    $db->prepare('SELECT id FROM aircraft WHERE id = ? FOR UPDATE')->execute([$raceId]);
    foreach (['C1', 'C2'] as $number) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--capacity-worker', (string) $raceId, $number], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start capacity worker.');
        }
        fclose($pipes[0]);
        $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        if (trim((string) fgets($pipes[1])) !== 'READY') {
            throw new RuntimeException('Capacity worker failed to start.');
        }
    }
    $db->commit();
    $results = [];
    foreach ($workers as $worker) {
        $results[] = trim(stream_get_contents($worker['stdout']));
        $errors = stream_get_contents($worker['stderr']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        if (proc_close($worker['process']) !== 0 || $errors !== '') {
            throw new RuntimeException('Capacity worker failed: ' . $errors);
        }
    }
    $workers = [];
    sort($results);
    $assert($results === ['CAPACITY', 'CREATED'] && (new Aircraft())->find($raceId)['seat_count'] == 1, 'simultaneous additions cannot overfill an aircraft');
    echo $checks . ' Module 3 checks passed.' . PHP_EOL;
} catch (Throwable $exception) {
    $failed = true;
    fwrite(STDERR, 'Module 3 test failed: ' . $exception->getMessage() . PHP_EOL);
} finally {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    foreach ($workers as $worker) {
        if (is_resource($worker['process'])) {
            proc_terminate($worker['process']);
            proc_close($worker['process']);
        }
    }
    if ($db instanceof PDO) {
        if ($aircraftIds) {
            $slots = implode(',', array_fill(0, count($aircraftIds), '?'));
            $db->prepare('DELETE FROM seats WHERE aircraft_id IN (' . $slots . ')')->execute($aircraftIds);
            $db->prepare('DELETE FROM aircraft WHERE id IN (' . $slots . ')')->execute($aircraftIds);
        }
        if ($airportIds) {
            $slots = implode(',', array_fill(0, count($airportIds), '?'));
            $db->prepare('DELETE FROM airports WHERE id IN (' . $slots . ')')->execute($airportIds);
        }
        $db->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([$adminEmail, $customerEmail]);
        echo 'Unique Module 3 test records removed.' . PHP_EOL;
    }
    foreach ($clients as $handle) {
        curl_close($handle);
    }
}
exit($failed ? 1 : 0);
