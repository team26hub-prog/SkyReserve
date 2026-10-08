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
$adminEmail = 'search-admin-' . $suffix . '@example.invalid';
$customerEmail = 'search-customer-' . $suffix . '@example.invalid';
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

$bookingIds = [];
try {
    $db = Database::connection();
    $insert = $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    foreach ([$adminEmail => 'admin', $customerEmail => 'customer'] as $email => $role) {
        $insert->execute(['Search test', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    $userId = (int) $db->lastInsertId();
    $guest = $client();
    $customer = $client();
    $form = $request($customer, 'GET', '/login');
    $assert($request($customer, 'POST', '/login', ['_token' => $token($form), 'email' => $customerEmail, 'password' => $password])['status'] === 303, 'customer signs in');
    foreach (['Origin', 'Destination'] as $name) {
        do {
            $code = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
            $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?');
            $query->execute([$code]);
        } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, ?, 'UTC')")->execute([$code, $name . ' <b>airport</b>', $name . ' city', 'Test']);
        $airportIds[] = (int) $db->lastInsertId();
    }
    foreach (['A' => 'active', 'B' => 'active', 'C' => 'maintenance'] as $prefix => $status) {
        $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity, status) VALUES (?, ?, 4, ?)')->execute([$prefix . '-' . $suffix, 'Test <b>aircraft</b>', $status]);
        $aircraftIds[] = (int) $db->lastInsertId();
    }
    $seatIds = [];
    foreach (['1A' => 'active', '1B' => 'active', '1C' => 'active', '1D' => 'inactive'] as $seat => $status) {
        $db->prepare('INSERT INTO seats (aircraft_id, seat_number, status) VALUES (?, ?, ?)')->execute([$aircraftIds[0], $seat, $status]);
        $seatIds[] = (int) $db->lastInsertId();
    }
    $db->prepare("INSERT INTO seats (aircraft_id, seat_number) VALUES (?, '1A')")->execute([$aircraftIds[2]]);
    $day = new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'));
    $date = $day->format('Y-m-d');
    $next = $day->modify('+1 day')->format('Y-m-d');
    $previous = $day->modify('-1 day')->format('Y-m-d');
    $fixtures = [
        'S' => [$date . ' 09:00:00', $date . ' 11:00:00', 'scheduled', $aircraftIds[0]],
        'E' => [$date . ' 00:00:00', $date . ' 02:00:00', 'scheduled', $aircraftIds[0]],
        'L' => [$date . ' 23:59:59', $next . ' 02:00:00', 'scheduled', $aircraftIds[0]],
        'D' => [$date . ' 10:00:00', $date . ' 12:00:00', 'delayed', $aircraftIds[0]],
        'C' => [$date . ' 10:00:00', $date . ' 12:00:00', 'cancelled', $aircraftIds[0]],
        'F' => [$date . ' 10:00:00', $date . ' 12:00:00', 'completed', $aircraftIds[0]],
        'O' => [$next . ' 00:00:00', $next . ' 02:00:00', 'scheduled', $aircraftIds[0]],
        'P' => [$previous . ' 23:59:59', $date . ' 02:00:00', 'scheduled', $aircraftIds[0]],
        'U' => [$date . ' 10:00:00', $date . ' 12:00:00', 'scheduled', $aircraftIds[1]],
        'M' => [$date . ' 10:00:00', $date . ' 12:00:00', 'scheduled', $aircraftIds[2]],
    ];
    $ids = [];
    foreach ($fixtures as $prefix => [$departure, $arrival, $status, $aircraft]) {
        $db->prepare('INSERT INTO flights (flight_number, origin_airport_id, destination_airport_id, aircraft_id, departure_at, arrival_at, base_fare, status) VALUES (?, ?, ?, ?, ?, ?, 1000.25, ?)')->execute([$prefix . strtoupper($suffix), $airportIds[0], $airportIds[1], $aircraft, $departure, $arrival, $status]);
        $ids[$prefix] = (int) $db->lastInsertId();
        $flightIds[] = $ids[$prefix];
    }
    // Existing allocation-table fixtures only; no booking endpoints/workflows are implemented.
    foreach (['S' => ['reserved', 'confirmed', 'released'], 'O' => ['released', 'released', 'reserved']] as $prefix => $statuses) {
        $db->prepare('INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount) VALUES (?, ?, ?, 1000.25)')->execute([$prefix . '-' . $suffix, $userId, $ids[$prefix]]);
        $bookingId = (int) $db->lastInsertId();
        $bookingIds[] = $bookingId;
        foreach ($statuses as $index => $status) {
            $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name) VALUES (?, 'Search', 'Fixture')")->execute([$bookingId]);
            $passenger = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO booking_seats (booking_id, passenger_id, flight_id, aircraft_id, seat_id, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$bookingId, $passenger, $ids[$prefix], $aircraftIds[0], $seatIds[$index], $status]);
        }
    }
    $criteria = ['from_airport_id' => $airportIds[0], 'to_airport_id' => $airportIds[1], 'travel_date' => $date];
    $path = '/flights?' . http_build_query($criteria);
    $snapshot = static function () use ($db, $flightIds): string {
        $query = $db->prepare('SELECT * FROM flights WHERE id IN (' . implode(',', array_fill(0, count($flightIds), '?')) . ') ORDER BY id');
        $query->execute($flightIds);
        return hash('sha256', json_encode($query->fetchAll()));
    };
    $before = $snapshot();
    $assert($request($guest, 'GET', '/flights')['status'] === 200, 'guest can open search form');
    $results = $request($customer, 'GET', $path);
    $assert($results['status'] === 200 && str_contains($results['body'], '3 flight(s) found'), 'valid search returns three scheduled flights');
    foreach (['E', 'S', 'L'] as $prefix) {
        $assert(str_contains($results['body'], $prefix . strtoupper($suffix)), 'matching flight included: ' . $prefix);
    }
    foreach (['D', 'C', 'F', 'O', 'P', 'U', 'M'] as $prefix) {
        $assert(!str_contains($results['body'], $prefix . strtoupper($suffix)), 'ineligible flight excluded: ' . $prefix);
    }
    $assert(strpos($results['body'], 'E' . strtoupper($suffix)) < strpos($results['body'], 'S' . strtoupper($suffix)) && strpos($results['body'], 'S' . strtoupper($suffix)) < strpos($results['body'], 'L' . strtoupper($suffix)), 'results ordered by departure');
    $assert(str_contains($results['body'], '&lt;b&gt;airport&lt;/b&gt;') && str_contains($results['body'], 'PKR 1000.25'), 'results display escaped route data and fare');
    $detail = $request($guest, 'GET', '/flights/show?id=' . $ids['S']);
    $assert($detail['status'] === 200 && preg_match('/Available seats<\/dt><dd>1<\/dd>/', $detail['body']) === 1, 'availability excludes inactive and occupied seats but includes released seats');
    $assert(str_contains($detail['body'], '&lt;b&gt;aircraft&lt;/b&gt;') && str_contains($detail['body'], $date . ' 09:00:00'), 'details display escaped aircraft and UTC times');
    $assert(!str_contains($detail['body'], '/admin/flights/update') && !str_contains($detail['body'], '/admin/flights/delete'), 'details provide no administrative mutation controls');
    $reverse = $criteria;
    [$reverse['from_airport_id'], $reverse['to_airport_id']] = [$reverse['to_airport_id'], $reverse['from_airport_id']];
    $none = $request($guest, 'GET', '/flights?' . http_build_query($reverse));
    $assert($none['status'] === 200 && str_contains($none['body'], 'No available scheduled flights'), 'no-result route displays helpful message');
    $otherDay = $criteria;
    $otherDay['travel_date'] = $next;
    $other = $request($guest, 'GET', '/flights?' . http_build_query($otherDay));
    $assert(str_contains($other['body'], 'O' . strtoupper($suffix)) && !str_contains($other['body'], 'S' . strtoupper($suffix)), 'date filtering changes results');
    foreach ([['to_airport_id', $airportIds[0]], ['from_airport_id', '9223372036854775807'], ['to_airport_id', ['1']], ['travel_date', '2030-02-30'], ['travel_date', '2000-01-01'], ['travel_date', ''], ['travel_date', ['tomorrow']]] as [$key, $value]) {
        $invalid = $criteria;
        $invalid[$key] = $value;
        $assert($request($guest, 'GET', '/flights?' . http_build_query($invalid))['status'] === 422, 'invalid search rejected: ' . $key);
    }
    foreach (['D', 'C', 'F', 'U', 'M'] as $prefix) {
        $assert($request($customer, 'GET', '/flights/show?id=' . $ids[$prefix])['status'] === 404, 'ineligible details hidden: ' . $prefix);
    }
    foreach (['id=9223372036854775807', 'id[]=1', 'id=bad'] as $query) {
        $assert($request($guest, 'GET', '/flights/show?' . $query)['status'] === 404, 'invalid details ID rejected');
    }
    $assert($request($guest, 'POST', '/flights', ['status' => 'cancelled'])['status'] === 405, 'POST search is rejected');
    $assert($request($guest, 'POST', '/flights/show?id=' . $ids['S'], ['status' => 'cancelled'])['status'] === 405, 'POST details is rejected');
    $assert($request($customer, 'POST', '/admin/flights/update?id=' . $ids['S'], ['status' => 'cancelled'])['status'] === 403, 'customer cannot mutate admin flights');
    $assert($before === $snapshot(), 'search and details leave flight data and timestamps unchanged');
    $db->prepare("UPDATE booking_seats SET status = 'confirmed' WHERE flight_id = ? AND seat_id = ?")->execute([$ids['S'], $seatIds[2]]);
    $soldOut = $request($guest, 'GET', $path);
    $assert(!str_contains($soldOut['body'], 'S' . strtoupper($suffix)), 'sold-out flights excluded after availability changes');
    $assert($request($guest, 'GET', '/flights/show?id=' . $ids['S'])['status'] === 404, 'sold-out details unavailable');
    $db->prepare("UPDATE airports SET status = 'inactive' WHERE id = ?")->execute([$airportIds[0]]);
    $assert($request($guest, 'GET', $path)['status'] === 422, 'inactive airport cannot be searched');
    $assert($request($guest, 'GET', '/flights/show?id=' . $ids['E'])['status'] === 404, 'inactive route details unavailable');
    echo $checks . ' Module 5 checks passed.' . PHP_EOL;
} catch (Throwable $exception) {
    $failed = true;
    fwrite(STDERR, 'Search test failed: ' . $exception->getMessage() . PHP_EOL);
} finally {
    if ($db instanceof PDO) {
        foreach ($bookingIds as $id) {
            $db->prepare('DELETE FROM booking_seats WHERE booking_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM passengers WHERE booking_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
        }
        foreach ($flightIds as $id) { $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$id]); }
        foreach ($aircraftIds as $id) {
            $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$id]);
        }
        foreach ($airportIds as $id) { $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]); }
        $db->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([$adminEmail, $customerEmail]);
        echo 'Unique Module 5 fixtures removed.' . PHP_EOL;
    }
    foreach ($clients as $handle) { curl_close($handle); }
}
exit($failed ? 1 : 0);

