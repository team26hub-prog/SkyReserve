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
$adminEmail = 'booking-admin-' . $suffix . '@example.invalid';
$customerEmail = 'booking-customer-' . $suffix . '@example.invalid';
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

$bookingToken = static function (array $response): string {
    if (!preg_match('/name="booking_token" value="([a-f0-9]{64})"/', $response['body'], $match)) { throw new RuntimeException('Booking token missing.'); }
    return $match[1];
};
$otherEmail = 'booking-other-' . $suffix . '@example.invalid';
$userIds = [];
try {
    $db = Database::connection();
    foreach ([$adminEmail => 'admin', $customerEmail => 'customer', $otherEmail => 'customer'] as $email => $role) {
        $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['Booking test', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $userIds[$email] = (int) $db->lastInsertId();
    }
    $guest = $client(); $customer = $client(); $admin = $client(); $other = $client();
    foreach ([[$customer, $customerEmail, '/login'], [$admin, $adminEmail, '/login'], [$other, $otherEmail, '/login']] as [$handle, $email, $login]) {
        $form = $request($handle, 'GET', $login);
        $assert($request($handle, 'POST', $login, ['_token' => $token($form), 'email' => $email, 'password' => $password])['status'] === 303, 'fixture user signs in');
    }
    foreach (['Origin', 'Destination'] as $name) {
        do { $code = chr(random_int(65,90)) . chr(random_int(65,90)) . chr(random_int(65,90)); $q = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $q->execute([$code]); } while ($q->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code, $name, $name]);
        $airportIds[] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 2)')->execute(['B-' . $suffix, 'Booking test']);
    $aircraftIds[] = $aircraft = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO seats (aircraft_id, seat_number) VALUES (?, '1A')")->execute([$aircraft]);
    $seatId = (int) $db->lastInsertId();
    $future = new DateTimeImmutable('tomorrow');
    $ids = [];
    foreach (['scheduled', 'cancelled', 'completed', 'delayed', 'past'] as $index => $status) {
        $day = $status === 'past' ? $future->modify('-2 days') : $future;
        $db->prepare('INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare, status) VALUES (?, ?, ?, ?, ?, ?, 1234.56, ?)')->execute(['B' . $index . strtoupper($suffix), $aircraft, $airportIds[0], $airportIds[1], $day->format('Y-m-d') . ' 10:00:00', $day->format('Y-m-d') . ' 12:00:00', $status === 'past' ? 'scheduled' : $status]);
        $ids[$status] = (int) $db->lastInsertId(); $flightIds[] = $ids[$status];
    }
    $id = $ids['scheduled'];
    $path = '/bookings?flight_id=' . $id;
    foreach ([['GET', '/bookings/create?flight_id=' . $id], ['POST', $path], ['GET', '/bookings/show?id=1']] as [$method, $url]) {
        $response = $request($guest, $method, $url);
        $assert($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/login', 'guest blocked: ' . $url);
        $assert($request($admin, $method, $url)['status'] === 403, 'admin cannot use customer booking route');
    }
    $form = $request($customer, 'GET', '/bookings/create?flight_id=' . $id);
    $assert($form['status'] === 200, 'customer can open passenger form');
    $fields = ['_token' => $token($form), 'booking_token' => $bookingToken($form), 'full_name' => 'Ali Khan', 'document_number' => '35202-1234567-1', 'date_of_birth' => '2000-02-29', 'gender' => 'male', 'phone' => '+92 300 1234567', 'user_id' => $userIds[$otherEmail], 'total_amount' => '0.01', 'status' => 'confirmed'];
    $withoutCsrf = $fields; unset($withoutCsrf['_token']);
    $assert($request($customer, 'POST', $path, $withoutCsrf)['status'] === 403, 'booking requires CSRF');
    $assert($request($customer, 'POST', $path, ['_token' => $fields['_token'], 'booking_token' => $fields['booking_token']])['status'] === 422, 'required passenger fields validated');
    foreach ([['full_name', '<script>'], ['full_name', ['Ali']], ['document_number', 'bad'], ['date_of_birth', '2000-02-30'], ['date_of_birth', '2100-01-01'], ['gender', 'invalid'], ['phone', '123']] as [$key, $value]) {
        $invalid = $fields; $invalid[$key] = $value;
        $assert($request($customer, 'POST', $path, $invalid)['status'] === 422, 'invalid passenger input rejected: ' . $key);
    }
    $forged = $fields; $forged['booking_token'] = str_repeat('a', 64);
    $assert($request($customer, 'POST', $path, $forged)['status'] === 409, 'unknown form token rejected');
    $response = $request($customer, 'POST', $path, $fields);
    $assert($response['status'] === 303 && preg_match('/^\/bookings\/show\?id=([0-9]+)$/', $response['headers']['location'] ?? '', $match), 'booking created and redirected to summary');
    $bookingId = (int) $match[1];
    $q = $db->prepare('SELECT * FROM bookings WHERE id = ?'); $q->execute([$bookingId]); $booking = $q->fetch();
    $assert($booking['user_id'] == $userIds[$customerEmail] && $booking['flight_id'] == $id && $booking['status'] === 'pending' && $booking['total_amount'] === '1234.56', 'customer, flight, fare, and status cannot be forged');
    $q = $db->prepare('SELECT * FROM passengers WHERE booking_id = ?'); $q->execute([$bookingId]); $passengers = $q->fetchAll();
    $assert(count($passengers) === 1 && $passengers[0]['full_name'] === 'Ali Khan' && $passengers[0]['document_number'] === '3520212345671' && $passengers[0]['gender'] === 'male' && $passengers[0]['phone'] === $fields['phone'], 'passenger linked with all details and normalized CNIC');
    $summary = $request($customer, 'GET', '/bookings/show?id=' . $bookingId);
    $assert($summary['status'] === 200 && str_contains($summary['body'], $booking['booking_reference']) && str_contains($summary['body'], 'Pending Payment') && str_contains($summary['body'], 'Ali Khan') && str_contains($summary['body'], '1234.56'), 'summary displays PNR, passenger, fare, and status');
    $assert($request($other, 'GET', '/bookings/show?id=' . $bookingId)['status'] === 404, 'another customer cannot read passenger details');
    $replay = $request($customer, 'POST', $path, $fields);
    $assert($replay['status'] === 303 && $replay['headers']['location'] === $response['headers']['location'], 'duplicate submission returns the original booking');
    $q = $db->prepare('SELECT COUNT(*) FROM bookings WHERE user_id = ?'); $q->execute([$userIds[$customerEmail]]);
    $assert((int) $q->fetchColumn() === 1, 'duplicate request creates no extra booking');
    $form2 = $request($customer, 'GET', '/bookings/create?flight_id=' . $id);
    $second = $fields; $second['booking_token'] = $bookingToken($form2); $second['document_number'] = 'AB1234567'; $second['full_name'] = 'Sara Ahmed'; $second['gender'] = 'female';
    $assert($request($customer, 'POST', $path, $second)['status'] === 303, 'new form accepts passport and creates a separate booking');
    $q = $db->prepare('SELECT COUNT(DISTINCT booking_reference) FROM bookings WHERE user_id = ?'); $q->execute([$userIds[$customerEmail]]);
    $assert((int) $q->fetchColumn() === 2, 'PNRs are unique');
    foreach (['cancelled', 'completed', 'delayed', 'past'] as $status) {
        $assert($request($customer, 'GET', '/bookings/create?flight_id=' . $ids[$status])['status'] === 409, 'unavailable flight blocked: ' . $status);
    }
    $assert($request($customer, 'GET', '/bookings/create?flight_id=9223372036854775807')['status'] === 409, 'nonexistent flight blocked');
    $assert($request($customer, 'GET', '/bookings/create?flight_id[]=1')['status'] === 404, 'malformed flight ID blocked');
    $staleForm = $request($customer, 'GET', '/bookings/create?flight_id=' . $id);
    $stale = $fields; $stale['booking_token'] = $bookingToken($staleForm);
    $db->prepare("UPDATE flights SET status = 'cancelled' WHERE id = ?")->execute([$id]);
    $assert($request($customer, 'POST', $path, $stale)['status'] === 409, 'flight cancelled after opening form cannot be booked');
    $assert($request($customer, 'POST', $path, $fields)['status'] === 303, 'completed submission replay stays idempotent after flight changes');
    $db->prepare("UPDATE flights SET status = 'scheduled' WHERE id = ?")->execute([$id]);
    $db->prepare("UPDATE seats SET status = 'inactive' WHERE id = ?")->execute([$seatId]);
    $assert($request($customer, 'POST', $path, $stale)['status'] === 409, 'availability rechecked at submission');
    $db->prepare("UPDATE seats SET status = 'active' WHERE id = ?")->execute([$seatId]);
    $invalidDbPassenger = ['full_name' => 'Rollback Test', 'document_number' => 'AB123456', 'date_of_birth' => '2000-01-01', 'gender' => 'male', 'phone' => str_repeat('1', 31)];
    try { (new App\Models\Booking())->create($userIds[$customerEmail], $id, hash('sha256', 'rollback-' . $suffix), $invalidDbPassenger); throw new RuntimeException('Expected passenger insert failure.'); }
    catch (PDOException) { $assert(true, 'passenger database failure rolls back transaction'); }
    $q->execute([$userIds[$customerEmail]]); $assert((int) $q->fetchColumn() === 2, 'rollback leaves no orphan booking');
    $q = $db->prepare('SELECT COUNT(*) FROM booking_seats WHERE booking_id IN (SELECT id FROM bookings WHERE user_id = ?)'); $q->execute([$userIds[$customerEmail]]);
    $assert((int) $q->fetchColumn() === 0, 'booking creates no seat allocation');
    $q = $db->prepare('SELECT COUNT(*) FROM payments WHERE booking_id IN (SELECT id FROM bookings WHERE user_id = ?)'); $q->execute([$userIds[$customerEmail]]);
    $assert((int) $q->fetchColumn() === 0, 'booking creates no payment submission');
    echo $checks . ' Module 6 checks passed.' . PHP_EOL;
} catch (Throwable $exception) { $failed = true; fwrite(STDERR, 'Booking test failed: ' . $exception->getMessage() . PHP_EOL); }
finally {
    if ($db instanceof PDO) {
        foreach ($userIds as $userId) {
            $db->prepare('DELETE FROM passengers WHERE booking_id IN (SELECT id FROM bookings WHERE user_id = ?)')->execute([$userId]);
            $db->prepare('DELETE FROM bookings WHERE user_id = ?')->execute([$userId]);
        }
        foreach ($flightIds as $id) { $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$id]); }
        foreach ($aircraftIds as $id) { $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$id]); $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$id]); }
        foreach ($airportIds as $id) { $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]); }
        $db->prepare('DELETE FROM users WHERE email IN (?, ?, ?)')->execute([$adminEmail, $customerEmail, $otherEmail]);
        echo 'Unique Module 6 fixtures removed.' . PHP_EOL;
    }
    foreach ($clients as $handle) { curl_close($handle); }
}
exit($failed ? 1 : 0);

