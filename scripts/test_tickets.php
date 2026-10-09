<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
use App\Models\Ticket;

if (($argv[1] ?? '') === '--worker') {
    try {
        $query = Database::connection()->prepare("SELECT id FROM bookings WHERE id = ? AND booking_reference LIKE 'M10-RACE-%'");
        $query->execute([(int) $argv[2]]);
        if (!$query->fetchColumn()) throw new RuntimeException('Race fixture not found.');
        echo 'READY' . PHP_EOL; flush();
        try { (new Ticket())->generate((int) $argv[2], ['id' => (int) $argv[3]]); echo 'ISSUED' . PHP_EOL; }
        catch (DomainException) { echo 'BLOCKED' . PHP_EOL; }
        exit(0);
    } catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . PHP_EOL); exit(1); }
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/'); $url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || !in_array($url['host'] ?? '', ['localhost','127.0.0.1'], true)
    || isset($url['user']) || isset($url['pass']) || !empty($url['path']) || isset($url['query']) || isset($url['fragment'])) {
    fwrite(STDERR, 'Use a local development server URL.' . PHP_EOL); exit(1);
}
if (!extension_loaded('curl')) { fwrite(STDERR, 'Tests require curl.' . PHP_EOL); exit(1); }
$db = null; $checks = 0; $failed = false; $users = $emails = $bookings = $airports = $workers = $clients = [];
$aircraftId = $flightId = null; $triggerCreated = false;
$suffix = bin2hex(random_bytes(3)); $password = 'Ticket-' . bin2hex(random_bytes(12)); $trigger = 'test_ticket_' . $suffix;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    ++$checks; echo 'PASS: ' . $message . PHP_EOL;
};
$client = static function () use (&$clients): CurlHandle {
    $handle = curl_init(); curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    $clients[] = $handle; return $handle;
};
$request = static function (CurlHandle $handle, string $method, string $path, array $fields = []) use ($base): array {
    $headers = []; curl_setopt($handle, CURLOPT_HTTPGET, true);
    if ($method === 'POST') curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt_array($handle, [CURLOPT_URL => $base . $path, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) { [$key,$value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); }
            return strlen($line);
        }]);
    $body = curl_exec($handle); if ($body === false) throw new RuntimeException(curl_error($handle));
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
};
$token = static function (array $response): string {
    if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $match)) throw new RuntimeException('CSRF token missing.');
    return $match[1];
};
try {
    $db = Database::connection();
    foreach (['customer','other','admin'] as $role) {
        $emails[$role] = "ticket-$role-$suffix@example.invalid";
        $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['Ticket ' . $role, $emails[$role], password_hash($password, PASSWORD_DEFAULT), $role === 'admin' ? 'admin' : 'customer']);
        $users[$role] = (int) $db->lastInsertId();
    }
    foreach (['Origin','Destination'] as $name) {
        do { $code = chr(random_int(65,90)) . chr(random_int(65,90)) . chr(random_int(65,90)); $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $query->execute([$code]); } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code, 'Ticket ' . $name, $name]); $airports[] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 10)')->execute(['T-' . $suffix, 'Ticket aircraft']); $aircraftId = (int) $db->lastInsertId();
    $seats = [];
    for ($i = 1; $i <= 10; ++$i) { $db->prepare('INSERT INTO seats (aircraft_id, seat_number, cabin_class) VALUES (?, ?, ?)')->execute([$aircraftId, $i . 'A', $i === 1 ? 'business' : 'economy']); $seats[] = (int) $db->lastInsertId(); }
    $day = gmdate('Y-m-d', time() + 172800);
    $db->prepare('INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare) VALUES (?, ?, ?, ?, ?, ?, 1200.50)')->execute(['T' . strtoupper($suffix), $aircraftId, $airports[0], $airports[1], $day . ' 10:00:00', $day . ' 12:00:00']); $flightId = (int) $db->lastInsertId();
    $make = static function (string $tag, array $seatIds) use ($db, $flightId, $aircraftId, $users, $suffix, &$bookings): array {
        $db->prepare("INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount, status) VALUES (?, ?, ?, 1200.50, 'confirmed')")->execute(['M10-' . $tag . '-' . $suffix, $users['customer'], $flightId]); $bookingId = (int) $db->lastInsertId(); $bookings[] = $bookingId;
        $passengers = $allocations = [];
        foreach ($seatIds as $index => $seatId) {
            $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name, full_name, document_number) VALUES (?, 'Ticket', 'Passenger', ?, 'AB123456')")->execute([$bookingId, '<b>Ticket Passenger ' . $index . '</b>']); $passengers[] = $passengerId = (int) $db->lastInsertId();
            $db->prepare("INSERT INTO booking_seats (booking_id, passenger_id, flight_id, aircraft_id, seat_id, status) VALUES (?, ?, ?, ?, ?, 'reserved')")->execute([$bookingId, $passengerId, $flightId, $aircraftId, $seatId]); $allocations[] = (int) $db->lastInsertId();
        }
        $db->prepare("INSERT INTO payments (booking_id, amount, method, status, reviewed_by, reviewed_at) VALUES (?, 1200.50, 'cash', 'verified', ?, UTC_TIMESTAMP())")->execute([$bookingId, $users['admin']]);
        return ['booking' => $bookingId, 'payment' => (int) $db->lastInsertId(), 'passengers' => $passengers, 'allocations' => $allocations];
    };
    $success = $make('OK', [$seats[0],$seats[1]]); $invalid = $make('INVALID', [$seats[2]]); $race = $make('RACE', [$seats[3]]); $adminFixture = $make('ADMIN', [$seats[4]]); $failure = $make('FAIL', [$seats[5],$seats[6]]);
    $guest = $client(); $customer = $client(); $other = $client(); $admin = $client();
    foreach ([[$customer,'customer','/login'],[$other,'other','/login'],[$admin,'admin','/login']] as [$handle,$role,$path]) {
        $form = $request($handle, 'GET', $path);
        $assert($request($handle, 'POST', $path, ['_token' => $token($form), 'email' => $emails[$role], 'password' => $password])['status'] === 303, 'fixture ' . $role . ' login');
    }
    $csrf = $token($request($customer, 'GET', '/profile')); $adminCsrf = $token($request($admin, 'GET', '/admin')); $otherCsrf = $token($request($other, 'GET', '/profile'));
    $generate = '/bookings/tickets?booking_id=' . $success['booking'];
    $assert(str_contains($request($customer, 'GET', '/bookings/show?id=' . $success['booking'])['body'], 'Generate ticket'), 'eligible booking summary offers generation');
    $assert($request($guest, 'POST', $generate)['status'] === 303, 'guest cannot generate');
    $assert($request($other, 'POST', $generate, ['_token' => $otherCsrf])['status'] === 404, 'another customer cannot generate');
    $assert($request($admin, 'POST', $generate, ['_token' => $adminCsrf])['status'] === 403, 'admin uses separate authorized route');
    $assert($request($customer, 'POST', $generate)['status'] === 403, 'generation requires CSRF');
    $assert($request($customer, 'GET', $generate)['status'] === 405, 'GET never generates tickets');
    $assert($request($customer, 'POST', '/bookings/tickets?booking_id[]=1', ['_token' => $csrf])['status'] === 404, 'malformed booking blocked');
    $assert($request($customer, 'POST', '/bookings/tickets?booking_id=2147483647', ['_token' => $csrf])['status'] === 404, 'missing booking blocked');
    $response = $request($customer, 'POST', $generate, ['_token' => $csrf, 'ticket_number' => 'FORGED', 'status' => 'void']);
    $issued = (new Ticket())->forBooking($success['booking']);
    $assert($response['status'] === 303 && count($issued) === 2, 'confirmed paid booking generates one ticket per passenger');
    $assert(count(array_unique(array_column($issued, 'ticket_number'))) === 2 && preg_match('/\ASR-T-[A-F0-9]{24}\z/', $issued[0]['ticket_number']) === 1 && $issued[0]['status'] === 'valid', 'unique server-generated ticket numbers and valid status');
    $assert($request($customer, 'POST', $generate, ['_token' => $csrf])['status'] === 409 && count((new Ticket())->forBooking($success['booking'])) === 2, 'duplicate generation blocked without new rows');
    foreach ([[$race['allocations'][0], $issued[0]['ticket_number']], [$success['allocations'][0], 'UNIQUE-' . $suffix]] as [$allocationId,$number]) {
        try { $db->prepare('INSERT INTO tickets (booking_seat_id, ticket_number) VALUES (?, ?)')->execute([$allocationId,$number]); throw new RuntimeException('Duplicate ticket constraint did not reject insert.'); }
        catch (PDOException $exception) { $assert($exception->getCode() === '23000', 'database protects ticket number / passenger allocation uniqueness'); }
    }
    $ticketId = (int) $issued[0]['id']; $show = '/tickets/show?id=' . $ticketId; $download = '/tickets/download?id=' . $ticketId;
    foreach ([$show,$download] as $path) {
        $assert($request($guest, 'GET', $path)['status'] === 303, 'guest blocked: ' . $path);
        $assert($request($other, 'GET', $path)['status'] === 404, 'other customer blocked: ' . $path);
        $assert($request($other, 'HEAD', $path)['status'] === 404, 'HEAD ownership protected: ' . $path);
        $assert($request($admin, 'GET', $path)['status'] === 403, 'customer endpoint role protected: ' . $path);
        $assert($request($customer, 'GET', '/admin' . $path)['status'] === 403, 'admin endpoint role protected: ' . $path);
        $assert($request($admin, 'GET', '/admin' . $path)['status'] === 200, 'authorized admin access: ' . $path);
    }
    $detail = $request($customer, 'GET', $show);
    foreach (['SkyReserve', $issued[0]['ticket_number'], 'M10-OK-' . $suffix, '&lt;b&gt;Ticket Passenger 0&lt;/b&gt;', 'AB123456', 'T' . strtoupper($suffix), 'Ticket Origin', 'Ticket Destination', $day . ' 10:00:00', $day . ' 12:00:00', '1A / Business', 'PKR 1200.50', 'Confirmed'] as $content) {
        $assert(str_contains($detail['body'], $content), 'ticket content: ' . $content);
    }
    $assert(!str_contains($detail['body'], '<b>Ticket Passenger') && str_contains($detail['body'], 'data-print-ticket') && str_contains($detail['body'], 'ticket.css'), 'escaped ticket with print control and styles');
    $html = $request($customer, 'GET', $download);
    $assert($html['status'] === 200 && str_contains($html['headers']['content-disposition'], '.html') && str_contains($html['headers']['content-type'], 'text/html') && str_contains($html['headers']['cache-control'], 'no-store'), 'download is private HTML attachment');
    $assert(str_contains($html['body'], '@media print') && str_contains($html['body'], $issued[0]['ticket_number']) && !str_contains($html['body'], '<script') && !str_contains($html['body'], 'href="/assets') && !str_contains($html['body'], 'class="site-header"'), 'download self-contained, printable, no scripts or navigation');
    $head = $request($customer, 'HEAD', $download); $assert($head['status'] === 200 && $head['body'] === '', 'download HEAD checks authorization without body');
    $assert(str_contains($request($customer, 'GET', '/bookings/show?id=' . $success['booking'])['body'], $show), 'booking summary links issued ticket');
    $assert(str_contains($request($admin, 'GET', '/admin/payments/show?id=' . $success['payment'])['body'], '/admin' . $show), 'related admin payment links issued ticket');
    foreach (['/tickets/show?id=2147483647','/tickets/download?id[]=1','/tickets/show?id=-1'] as $path) $assert($request($customer, 'GET', $path)['status'] === 404, 'invalid ticket lookup blocked: ' . $path);
    $invalidPath = '/bookings/tickets?booking_id=' . $invalid['booking'];
    foreach (['pending','payment_submitted','cancelled','expired'] as $status) {
        $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status, $invalid['booking']]);
        $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'unconfirmed booking blocked: ' . $status);
    }
    $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$invalid['booking']]);
    foreach (['pending','rejected','refunded'] as $status) {
        $db->prepare('UPDATE payments SET status = ? WHERE id = ?')->execute([$status, $invalid['payment']]);
        $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'unverified payment blocked: ' . $status);
    }
    $db->prepare("UPDATE payments SET status = 'verified' WHERE id = ?")->execute([$invalid['payment']]);
    $db->prepare("UPDATE booking_seats SET status = 'released' WHERE id = ?")->execute([$invalid['allocations'][0]]);
    $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'released seat blocked');
    $db->prepare("UPDATE booking_seats SET status = 'reserved' WHERE id = ?")->execute([$invalid['allocations'][0]]);
    $db->prepare("UPDATE seats SET status = 'inactive' WHERE id = ?")->execute([$seats[2]]);
    $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'inactive seat blocked');
    $db->prepare("UPDATE seats SET status = 'active' WHERE id = ?")->execute([$seats[2]]);
    $db->prepare("UPDATE passengers SET document_number = NULL WHERE id = ?")->execute([$invalid['passengers'][0]]);
    $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'missing passenger identity blocked');
    $db->prepare("UPDATE passengers SET document_number = 'AB123456', status = 'cancelled' WHERE id = ?")->execute([$invalid['passengers'][0]]);
    $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'inactive passenger blocked');
    $db->prepare("UPDATE passengers SET status = 'active' WHERE id = ?")->execute([$invalid['passengers'][0]]);
    foreach (['cancelled','completed'] as $status) {
        $db->prepare('UPDATE flights SET status = ? WHERE id = ?')->execute([$status, $flightId]);
        $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'unavailable flight blocks generation: ' . $status);
    }
    $db->prepare("UPDATE flights SET status = 'scheduled', departure_at = '2000-01-01 10:00:00' WHERE id = ?")->execute([$flightId]);
    $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'departed flight blocks generation');
    $assert($request($customer, 'GET', $show)['status'] === 200, 'issued ticket remains viewable after departure');
    $db->prepare('UPDATE flights SET departure_at = ? WHERE id = ?')->execute([$day . ' 10:00:00', $flightId]);
    $assert((new Ticket())->forBooking($invalid['booking']) === [], 'invalid attempts create no tickets');
    try { $db->prepare('UPDATE booking_seats SET aircraft_id = 0 WHERE id = ?')->execute([$invalid['allocations'][0]]); throw new RuntimeException('Wrong-aircraft assignment accepted.'); }
    catch (PDOException $exception) { $assert($exception->getCode() === '23000', 'existing composite foreign keys block wrong-aircraft allocation'); }
    $db->prepare('DELETE FROM booking_seats WHERE id = ?')->execute([$invalid['allocations'][0]]);
    $assert($request($customer, 'POST', $invalidPath, ['_token' => $csrf])['status'] === 409, 'missing seat assignment blocks generation');
    $empty = $make('EMPTY', []);
    $assert($request($customer, 'POST', '/bookings/tickets?booking_id=' . $empty['booking'], ['_token' => $csrf])['status'] === 409, 'booking without passengers blocks generation');
    $adminPath = '/admin/bookings/tickets?booking_id=' . $adminFixture['booking'];
    $assert($request($customer, 'POST', $adminPath, ['_token' => $csrf])['status'] === 403, 'customer cannot use admin generation');
    $assert($request($admin, 'POST', $adminPath)['status'] === 403, 'admin generation requires CSRF');
    $assert($request($admin, 'POST', $adminPath, ['_token' => $adminCsrf])['status'] === 303, 'authorized admin can issue from verified payment flow');
    $db->exec("CREATE TRIGGER $trigger BEFORE INSERT ON tickets FOR EACH ROW BEGIN IF NEW.booking_seat_id = {$failure['allocations'][1]} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test ticket insert failure'; END IF; END"); $triggerCreated = true;
    $response = $request($customer, 'POST', '/bookings/tickets?booking_id=' . $failure['booking'], ['_token' => $csrf]);
    $assert($response['status'] === 503 && !str_contains($response['body'], BASE_PATH) && (new Ticket())->forBooking($failure['booking']) === [], 'failed second insert rolls back every passenger ticket safely');
    $db->exec("DROP TRIGGER $trigger"); $triggerCreated = false;
    $db->beginTransaction(); $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE')->execute([$flightId]);
    foreach ([1,2] as $workerNumber) {
        $pipes = []; $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $race['booking'], (string) $users['customer']], [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Race process failed.');
        fclose($pipes[0]); $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        if (trim((string) fgets($pipes[1])) !== 'READY') throw new RuntimeException('Race worker not ready.');
    }
    $db->commit(); $results = [];
    foreach ($workers as $worker) {
        $results[] = trim(stream_get_contents($worker['stdout'])); $errors = stream_get_contents($worker['stderr']); fclose($worker['stdout']); fclose($worker['stderr']);
        if (proc_close($worker['process']) !== 0 || $errors !== '') throw new RuntimeException('Race failed: ' . $errors);
    }
    $workers = []; sort($results);
    $assert($results === ['BLOCKED','ISSUED'] && count((new Ticket())->forBooking($race['booking'])) === 1, 'concurrent generation has one winner and one ticket');
    $assert((new Ticket())->findAuthorized($ticketId, ['id' => $users['other'], 'role' => 'admin']) === null, 'model reads actual user role, ignoring forged admin role');
    try { (new Ticket())->generate($invalid['booking'], ['id' => $users['other'], 'role' => 'admin']); throw new RuntimeException('Unauthorized model generation succeeded.'); }
    catch (OutOfBoundsException) { $assert(true, 'model enforces booking ownership'); }
    $query = $db->prepare('SELECT status FROM bookings WHERE id = ?'); $query->execute([$success['booking']]);
    $assert($query->fetchColumn() === 'confirmed', 'issuance preserves confirmed booking');
    $query = $db->prepare('SELECT status FROM booking_seats WHERE id = ?'); $query->execute([$success['allocations'][0]]);
    $assert($query->fetchColumn() === 'reserved', 'issuance preserves existing seat reservation');
    echo "$checks Module 10 checks passed." . PHP_EOL;
} catch (Throwable $exception) { $failed = true; fwrite(STDERR, 'Ticket test failed: ' . $exception->getMessage() . PHP_EOL); }
finally {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    foreach ($workers as $worker) if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); }
    if ($db instanceof PDO) {
        if ($triggerCreated) $db->exec("DROP TRIGGER $trigger");
        foreach ($bookings as $id) {
            $db->prepare('DELETE FROM tickets WHERE booking_seat_id IN (SELECT id FROM booking_seats WHERE booking_id = ?)')->execute([$id]);
            foreach (['payments','booking_seats','passengers'] as $table) $db->prepare("DELETE FROM $table WHERE booking_id = ?")->execute([$id]);
            $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
        }
        if ($flightId) $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$flightId]);
        if ($aircraftId) { $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$aircraftId]); $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$aircraftId]); }
        foreach ($airports as $id) $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]);
        foreach ($emails as $email) $db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    }
    foreach ($clients as $handle) curl_close($handle);
    echo 'Unique Module 10 fixtures removed.' . PHP_EOL;
}
exit($failed ? 1 : 0);
