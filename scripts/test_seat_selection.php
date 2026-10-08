<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

if (($argv[1] ?? '') === '--worker') {
    try {
        $db = Database::connection();
        $query = $db->prepare("SELECT * FROM bookings WHERE id = ? AND booking_reference LIKE 'RACE-%'");
        $query->execute([(int) $argv[2]]); $booking = $query->fetch();
        if (!$booking) { throw new RuntimeException('Race fixture not found.'); }
        echo 'READY' . PHP_EOL; flush();
        try { (new App\Models\BookingSeat())->assign((int) $booking['id'], (int) $booking['user_id'], (int) $argv[3], (int) $argv[4]); echo 'ASSIGNED' . PHP_EOL; }
        catch (DomainException) { echo 'BLOCKED' . PHP_EOL; }
        exit(0);
    } catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . PHP_EOL); exit(1); }
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
$suffix = bin2hex(random_bytes(5));
$number = 'T' . strtoupper($suffix);
$adminEmail = 'seats-admin-' . $suffix . '@example.invalid';
$customerEmail = 'seats-customer-' . $suffix . '@example.invalid';
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

$bookingIds = $workers = [];
$otherEmail = 'seats-other-' . $suffix . '@example.invalid';
try {
    $db = Database::connection(); $users = [];
    foreach ([$adminEmail => 'admin', $customerEmail => 'customer', $otherEmail => 'customer'] as $email => $role) {
        $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['Seat test', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $users[$email] = (int) $db->lastInsertId();
    }
    $guest = $client(); $customer = $client(); $other = $client(); $admin = $client();
    foreach ([[$customer, $customerEmail, '/login'], [$other, $otherEmail, '/login'], [$admin, $adminEmail, '/admin/login']] as [$handle, $email, $login]) {
        $form = $request($handle, 'GET', $login);
        $assert($request($handle, 'POST', $login, ['_token' => $token($form), 'email' => $email, 'password' => $password])['status'] === 303, 'fixture user signs in');
    }
    foreach (['Origin', 'Destination'] as $name) {
        do { $code = chr(random_int(65,90)) . chr(random_int(65,90)) . chr(random_int(65,90)); $q = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $q->execute([$code]); } while ($q->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code, $name, $name]); $airportIds[] = (int) $db->lastInsertId();
    }
    foreach (['A-', 'B-'] as $prefix) { $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 5)')->execute([$prefix . $suffix, 'Seat test']); $aircraftIds[] = (int) $db->lastInsertId(); }
    $seatIds = [];
    foreach (['1A' => 'active', '1B' => 'active', '1C' => 'active', '2A' => 'active', '2B' => 'inactive'] as $number => $status) {
        $db->prepare('INSERT INTO seats (aircraft_id, seat_number, status) VALUES (?, ?, ?)')->execute([$aircraftIds[0], $number, $status]); $seatIds[$number] = (int) $db->lastInsertId();
    }
    $db->prepare("INSERT INTO seats (aircraft_id, seat_number) VALUES (?, '9A')")->execute([$aircraftIds[1]]); $wrongSeat = (int) $db->lastInsertId();
    $day = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $db->prepare("INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare) VALUES (?, ?, ?, ?, ?, ?, 1000)")->execute(['S' . strtoupper($suffix), $aircraftIds[0], $airportIds[0], $airportIds[1], $day . ' 10:00:00', $day . ' 12:00:00']);
    $flightIds[] = $flightId = (int) $db->lastInsertId();
    $passengers = [];
    foreach ([0,1,2,3] as $index) {
        $db->prepare("INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount) VALUES (?, ?, ?, 1000)")->execute(['RACE-' . $index . '-' . $suffix, $users[$customerEmail], $flightId]);
        $bookingIds[] = $bookingId = (int) $db->lastInsertId();
        $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name, full_name) VALUES (?, 'Seat', 'Tester', 'Seat Tester')")->execute([$bookingId]); $passengers[] = (int) $db->lastInsertId();
    }
    $path = '/bookings/seats?booking_id=' . $bookingIds[0];
    foreach (['GET','POST'] as $method) {
        $assert($request($guest, $method, $path)['status'] === 303, 'guest blocked: ' . $method);
        $assert($request($admin, $method, $path)['status'] === 403, 'admin blocked: ' . $method);
        $assert($request($other, $method, $path, ['_token' => $token($request($other, 'GET', '/profile')), 'seat_id' => $seatIds['1A'], 'passenger_id' => $passengers[0]])['status'] === 404, 'non-owner blocked: ' . $method);
    }
    $map = $request($customer, 'GET', $path); $csrf = $token($map);
    $assert($map['status'] === 200 && str_contains($map['body'], '1A') && !str_contains($map['body'], '9A'), 'seat map uses the booked flight aircraft');
    $assert($request($customer, 'GET', '/bookings/seats?booking_id=0')['status'] === 404, 'invalid booking ID blocked');
    $fields = ['_token' => $csrf, 'passenger_id' => $passengers[0], 'seat_id' => $seatIds['1A']];
    $assert($request($customer, 'POST', $path, ['seat_id' => $seatIds['1A']])['status'] === 403, 'CSRF required');
    $assert($request($customer, 'POST', $path, ['_token' => $csrf])['status'] === 422, 'selection required');
    foreach ([$wrongSeat, $seatIds['2B']] as $invalidSeat) { $invalid = $fields; $invalid['seat_id'] = $invalidSeat; $assert($request($customer, 'POST', $path, $invalid)['status'] === 409, 'wrong-aircraft/inactive seat blocked'); }
    $invalid = $fields; $invalid['passenger_id'] = $passengers[1];
    $assert($request($customer, 'POST', $path, $invalid)['status'] === 409, 'passenger from another booking blocked');
    $invalid = $fields; $invalid['seat_id'] = 2147483647;
    $assert($request($customer, 'POST', $path, $invalid)['status'] === 409, 'nonexistent seat blocked');
    $assert($request($customer, 'POST', $path, $fields)['status'] === 303, 'seat assignment succeeds');
    $assert($request($customer, 'POST', $path, $fields)['status'] === 409, 'duplicate submission blocked');
    $different = $fields; $different['seat_id'] = $seatIds['1B'];
    $assert($request($customer, 'POST', $path, $different)['status'] === 409, 'second seat for passenger blocked');
    $summary = $request($customer, 'GET', '/bookings/show?id=' . $bookingIds[0]);
    $assert($summary['status'] === 200 && str_contains($summary['body'], '1A — Economy'), 'summary shows selected seat and class');
    $map = $request($customer, 'GET', $path);
    $assert(str_contains($map['body'], 'selected-seat') && str_contains($map['body'], 'Selected'), 'saved selection displayed on map');
    $second = $fields; $second['passenger_id'] = $passengers[1];
    $path2 = '/bookings/seats?booking_id=' . $bookingIds[1];
    $assert($request($customer, 'POST', $path2, $second)['status'] === 409, 'already booked seat cannot be taken by another booking');
    foreach (['cancelled', 'expired'] as $status) {
        $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status, $bookingIds[1]]);
        $assert($request($customer, 'GET', $path2)['status'] === 409, 'invalid booking state blocked: ' . $status);
    }
    $db->prepare("UPDATE bookings SET status = 'pending' WHERE id = ?")->execute([$bookingIds[1]]);
    $db->prepare("UPDATE bookings SET expires_at = '2000-01-01 00:00:00' WHERE id = ?")->execute([$bookingIds[1]]);
    $assert($request($customer, 'GET', $path2)['status'] === 409, 'expired booking deadline blocked');
    $db->prepare('UPDATE bookings SET expires_at = NULL WHERE id = ?')->execute([$bookingIds[1]]);
    $db->prepare("UPDATE aircraft SET status = 'inactive' WHERE id = ?")->execute([$aircraftIds[0]]);
    $assert($request($customer, 'GET', $path2)['status'] === 409, 'inactive aircraft blocked');
    $db->prepare("UPDATE aircraft SET status = 'active' WHERE id = ?")->execute([$aircraftIds[0]]);
    $db->prepare("UPDATE flights SET status = 'delayed' WHERE id = ?")->execute([$flightId]);
    $assert($request($customer, 'GET', $path2)['status'] === 200, 'upcoming delayed flight remains valid for an existing booking');
    foreach (['cancelled','completed'] as $status) {
        $db->prepare('UPDATE flights SET status = ? WHERE id = ?')->execute([$status, $flightId]);
        $assert($request($customer, 'POST', $path2, $second)['status'] === 409, 'invalid flight state blocks submission: ' . $status);
    }
    $db->prepare("UPDATE flights SET status = 'scheduled', departure_at = '2000-01-01 10:00:00' WHERE id = ?")->execute([$flightId]);
    $assert($request($customer, 'GET', $path2)['status'] === 409, 'past flight blocked');
    $db->prepare('UPDATE flights SET departure_at = ? WHERE id = ?')->execute([$day . ' 10:00:00', $flightId]);
    // Gate two independent PDO processes on the flight lock (not PHP session serialization).
    $db->beginTransaction(); $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE')->execute([$flightId]);
    foreach ([2,3] as $index) {
        $pipes = []; $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $bookingIds[$index], (string) $passengers[$index], (string) $seatIds['1C']], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Worker could not start.'); }
        fclose($pipes[0]); $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        if (trim((string) fgets($pipes[1])) !== 'READY') { throw new RuntimeException('Worker readiness failed.'); }
    }
    $db->commit(); $results = [];
    foreach ($workers as $worker) {
        $results[] = trim(stream_get_contents($worker['stdout'])); $errors = stream_get_contents($worker['stderr']); fclose($worker['stdout']); fclose($worker['stderr']);
        if (proc_close($worker['process']) !== 0 || $errors !== '') { throw new RuntimeException('Worker failed: ' . $errors); }
    }
    $workers = []; sort($results);
    $assert($results === ['ASSIGNED','BLOCKED'], 'concurrent race yields exactly one winner');
    $query = $db->prepare('SELECT COUNT(*) FROM booking_seats WHERE flight_id = ? AND occupied_seat_id = ?'); $query->execute([$flightId, $seatIds['1C']]);
    $assert((int) $query->fetchColumn() === 1, 'only one active allocation exists for raced seat');
    $query = $db->prepare('SELECT COUNT(*) FROM booking_seats WHERE booking_id = ?'); $query->execute([$bookingIds[0]]);
    $assert((int) $query->fetchColumn() === 1, 'duplicate attempts leave exactly one passenger assignment');
    $query = $db->prepare('SELECT status FROM bookings WHERE id = ?'); $query->execute([$bookingIds[0]]);
    $assert($query->fetchColumn() === 'pending', 'seat selection preserves pending payment status');
    $query = $db->prepare('SELECT COUNT(*) FROM payments WHERE booking_id IN (' . implode(',', array_fill(0, count($bookingIds), '?')) . ')'); $query->execute($bookingIds);
    $assert((int) $query->fetchColumn() === 0, 'selection creates no payment records');
    echo $checks . ' Module 7 checks passed.' . PHP_EOL;
} catch (Throwable $exception) { $failed = true; fwrite(STDERR, 'Seat test failed: ' . $exception->getMessage() . PHP_EOL); }
finally {
    if ($db instanceof PDO && $db->inTransaction()) { $db->rollBack(); }
    foreach ($workers as $worker) { if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); } }
    if ($db instanceof PDO) {
        foreach ($bookingIds as $id) { $db->prepare('DELETE FROM booking_seats WHERE booking_id = ?')->execute([$id]); $db->prepare('DELETE FROM passengers WHERE booking_id = ?')->execute([$id]); $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]); }
        foreach ($flightIds as $id) { $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$id]); }
        foreach ($aircraftIds as $id) { $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$id]); $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$id]); }
        foreach ($airportIds as $id) { $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]); }
        $db->prepare('DELETE FROM users WHERE email IN (?, ?, ?)')->execute([$adminEmail, $customerEmail, $otherEmail]); echo 'Unique Module 7 fixtures removed.' . PHP_EOL;
    }
    foreach ($clients as $handle) { curl_close($handle); }
}
exit($failed ? 1 : 0);

