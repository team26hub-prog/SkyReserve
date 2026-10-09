<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
use App\Core\PaymentReceipt;
use App\Models\Payment;

if (($argv[1] ?? '') === '--worker') {
    try {
        $db = Database::connection();
        $query = $db->prepare("SELECT p.id FROM payments p JOIN bookings b ON b.id = p.booking_id WHERE p.id = ? AND b.booking_reference LIKE 'M9-RACE-%'");
        $query->execute([(int) $argv[2]]);
        if (!$query->fetchColumn()) throw new RuntimeException('Race fixture not found.');
        echo 'READY' . PHP_EOL; flush();
        try { (new Payment())->review((int) $argv[2], (int) $argv[3], $argv[4]); echo 'REVIEWED' . PHP_EOL; }
        catch (DomainException) { echo 'BLOCKED' . PHP_EOL; }
        exit(0);
    } catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . PHP_EOL); exit(1); }
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || !in_array($url['host'] ?? '', ['127.0.0.1','localhost'], true)
    || isset($url['user']) || isset($url['pass']) || !empty($url['path']) || isset($url['query']) || isset($url['fragment'])) {
    fwrite(STDERR, 'Use a local development server URL.' . PHP_EOL); exit(1);
}
if (!extension_loaded('curl') || !extension_loaded('gd')) { fwrite(STDERR, 'Tests require curl and GD.' . PHP_EOL); exit(1); }
$suffix = bin2hex(random_bytes(5)); $password = 'Review-' . bin2hex(random_bytes(12));
$emails = ['admin' => "review-admin-$suffix@example.invalid", 'customer' => "review-customer-$suffix@example.invalid", 'other' => "review-other-$suffix@example.invalid"];
$db = null; $checks = 0; $failed = false; $triggerCreated = false; $trigger = 'test_review_' . $suffix;
$clients = $bookingIds = $airportIds = $aircraftIds = $flightIds = $workers = [];
$receiptRelative = 'payment_receipts/' . bin2hex(random_bytes(24)) . '.png';
$receiptPath = BASE_PATH . '/storage/' . $receiptRelative;
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
            if (str_contains($line, ':')) { [$key, $value] = explode(':', $line, 2); $headers[strtolower(trim($key))] = trim($value); }
            return strlen($line);
        },
    ]);
    $body = curl_exec($handle);
    if ($body === false) throw new RuntimeException(curl_error($handle));
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
};
$token = static function (array $response): string {
    if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $matches)) throw new RuntimeException('CSRF token missing.');
    return $matches[1];
};
try {
    $db = Database::connection(); $users = [];
    foreach ($emails as $role => $email) {
        $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['<b>Review ' . $role . '</b>', $email, password_hash($password, PASSWORD_DEFAULT), $role === 'admin' ? 'admin' : 'customer']);
        $users[$role] = (int) $db->lastInsertId();
    }
    foreach (['Origin','Destination'] as $city) {
        do { $code = chr(random_int(65,90)) . chr(random_int(65,90)) . chr(random_int(65,90)); $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $query->execute([$code]); } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code, $city, $city]); $airportIds[] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 1)')->execute(['R-' . $suffix, 'Review aircraft']); $aircraftIds[] = $aircraftId = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO seats (aircraft_id, seat_number) VALUES (?, '1A')")->execute([$aircraftId]); $seatId = (int) $db->lastInsertId();
    $day = gmdate('Y-m-d', time() + 172800);
    $db->prepare('INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare) VALUES (?, ?, ?, ?, ?, ?, 1000.25)')->execute(['R' . strtoupper($suffix), $aircraftId, $airportIds[0], $airportIds[1], $day . ' 10:00:00', $day . ' 12:00:00']); $flightIds[] = $flightId = (int) $db->lastInsertId();
    $makePayment = static function (string $tag, ?string $proof = null) use ($db, $users, $flightId, $suffix, &$bookingIds): array {
        $db->prepare("INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount, status) VALUES (?, ?, ?, 1000.25, 'payment_submitted')")->execute(['M9-' . substr($tag, 0, 6) . '-' . $suffix, $users['customer'], $flightId]); $bookingId = (int) $db->lastInsertId(); $bookingIds[] = $bookingId;
        $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name, full_name, document_number) VALUES (?, 'Review', 'Passenger', '<i>Review Passenger</i>', 'AB123456')")->execute([$bookingId]); $passengerId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO payments (booking_id, amount, method, transaction_reference, payment_date, proof_path) VALUES (?, 1000.25, ?, ?, ?, ?)')->execute([$bookingId, 'bank_transfer', 'REF-' . $tag . '-' . $suffix, gmdate('Y-m-d'), $proof]);
        return ['payment' => (int) $db->lastInsertId(), 'booking' => $bookingId, 'passenger' => $passengerId];
    };
    $image = imagecreatetruecolor(20,20); imagepng($image, $receiptPath); imagedestroy($image);
    $verify = $makePayment('VERIFY', $receiptRelative); $reject = $makePayment('REJECT'); $optional = $makePayment('EMPTY'); $invalid = $makePayment('INVALID'); $failure = $makePayment('FAIL'); $race = $makePayment('RACE');
    $db->prepare("INSERT INTO booking_seats (booking_id, passenger_id, flight_id, aircraft_id, seat_id, status) VALUES (?, ?, ?, ?, ?, 'reserved')")->execute([$verify['booking'], $verify['passenger'], $flightId, $aircraftId, $seatId]);
    $guest = $client(); $admin = $client(); $customer = $client(); $other = $client();
    foreach ([[$admin,'admin','/login'], [$customer,'customer','/login'], [$other,'other','/login']] as [$handle,$role,$login]) {
        $form = $request($handle, 'GET', $login);
        $assert($request($handle, 'POST', $login, ['_token' => $token($form), 'email' => $emails[$role], 'password' => $password])['status'] === 303, 'fixture ' . $role . ' login');
    }
    $csrf = $token($request($admin, 'GET', '/admin'));
    foreach ([['GET','/admin/payments'], ['GET','/admin/payments/show?id=' . $verify['payment']], ['GET','/admin/payments/receipt?id=' . $verify['payment']], ['HEAD','/admin/payments/receipt?id=' . $verify['payment']], ['POST','/admin/payments/verify?id=' . $verify['payment']], ['POST','/admin/payments/reject?id=' . $verify['payment']]] as [$method,$path]) {
        $assert($request($guest, $method, $path)['status'] === 303, 'guest blocked: ' . $method . ' ' . $path);
        $customerToken = $token($request($customer, 'GET', '/profile'));
        $assert($request($customer, $method, $path, ['_token' => $customerToken])['status'] === 403, 'customer blocked: ' . $method . ' ' . $path);
    }
    $list = $request($admin, 'GET', '/admin/payments');
    $assert($list['status'] === 200 && str_contains($list['body'], 'M9-VERIFY-' . $suffix) && str_contains($list['body'], '&lt;b&gt;Review customer&lt;/b&gt;'), 'pending list displays submitted payments and escaped customer');
    $assert(str_contains($request($admin, 'GET', '/admin')['body'], (string) (new Payment())->pendingCount() . ' pending payment(s)'), 'dashboard shows pending count');
    foreach (['bad', 'pending%27', ''] as $status) $assert($request($admin, 'GET', '/admin/payments?status=' . $status)['status'] === 422, 'invalid filter blocked: ' . $status);
    $assert($request($admin, 'GET', '/admin/payments?status[]=pending')['status'] === 422, 'array filter blocked');
    $detail = $request($admin, 'GET', '/admin/payments/show?id=' . $verify['payment']);
    $assert($detail['status'] === 200 && str_contains($detail['body'], $emails['customer']) && str_contains($detail['body'], 'M9-VERIFY-' . $suffix) && str_contains($detail['body'], 'AB123456') && str_contains($detail['body'], '&lt;i&gt;Review Passenger&lt;/i&gt;') && str_contains($detail['body'], 'Amount due') && str_contains($detail['body'], 'Amount submitted') && str_contains($detail['body'], 'Bank transfer'), 'details show customer, booking, flight, passenger, payment data safely');
    $assert(!str_contains($detail['body'], $receiptRelative) && str_contains($detail['body'], '/admin/payments/receipt?id=' . $verify['payment']), 'detail links protected receipt without storage path');
    $receipt = $request($admin, 'GET', '/admin/payments/receipt?id=' . $verify['payment']);
    $assert($receipt['status'] === 200 && $receipt['headers']['content-type'] === 'image/png' && str_contains($receipt['headers']['cache-control'], 'no-store') && $receipt['headers']['x-content-type-options'] === 'nosniff' && getimagesizefromstring($receipt['body']) !== false, 'admin can view private valid receipt');
    $head = $request($admin, 'HEAD', '/admin/payments/receipt?id=' . $verify['payment']);
    $assert($head['status'] === 200 && $head['body'] === '', 'admin receipt HEAD returns no body');
    $assert($request($other, 'GET', '/payments/receipt?id=' . $verify['payment'])['status'] === 404, 'other customer cannot access receipt on customer route');
    $assert($request($customer, 'GET', '/payments/receipt?id=' . $verify['payment'])['status'] === 200, 'owning customer keeps receipt access');
    $assert($request($admin, 'GET', '/admin/payments/receipt?id=' . $reject['payment'])['status'] === 404, 'payment without receipt returns 404');
    foreach (['show','receipt','verify','reject'] as $action) {
        $method = in_array($action, ['verify','reject'], true) ? 'POST' : 'GET';
        $assert($request($admin, $method, '/admin/payments/' . $action . '?id=2147483647', ['_token' => $csrf])['status'] === 404, 'missing payment blocked: ' . $action);
    }
    $assert($request($admin, 'GET', '/admin/payments/show?id[]=1')['status'] === 404, 'malformed payment ID blocked');
    foreach (['verify','reject'] as $action) {
        $assert($request($admin, 'POST', '/admin/payments/' . $action . '?id=' . $verify['payment'])['status'] === 403, 'CSRF required: ' . $action);
        $assert($request($admin, 'GET', '/admin/payments/' . $action . '?id=' . $verify['payment'])['status'] === 405, 'review requires POST: ' . $action);
    }
    $assert($request($admin, 'POST', '/admin/payments/verify?id=' . $verify['payment'], ['_token' => $csrf, 'reviewed_by' => $users['other'], 'status' => 'rejected'])['status'] === 303, 'verification succeeds using authenticated admin and route decision');
    $verified = (new Payment())->findForAdmin($verify['payment']);
    $assert($verified['status'] === 'verified' && $verified['booking_status'] === 'confirmed' && (int) $verified['reviewed_by'] === $users['admin'] && $verified['reviewed_at'] !== null, 'verification atomically confirms booking and records reviewer/time');
    $metadata = [$verified['reviewed_by'], $verified['reviewed_at']];
    foreach (['verify','reject'] as $action) $assert($request($admin, 'POST', '/admin/payments/' . $action . '?id=' . $verify['payment'], ['_token' => $csrf])['status'] === 409, 'verified payment cannot transition again: ' . $action);
    $verified = (new Payment())->findForAdmin($verify['payment']);
    $assert([$verified['reviewed_by'], $verified['reviewed_at']] === $metadata && $verified['booking_status'] === 'confirmed', 'duplicate review preserves audit data and confirmed booking');
    $reason = '<script>Not a valid receipt</script>';
    $assert($request($admin, 'POST', '/admin/payments/reject?id=' . $reject['payment'], ['_token' => $csrf, 'reason' => $reason])['status'] === 303, 'rejection succeeds with optional reason');
    $rejected = (new Payment())->findForAdmin($reject['payment']);
    $assert($rejected['status'] === 'rejected' && $rejected['booking_status'] === 'pending' && $rejected['active_booking_id'] === null && $rejected['review_notes'] === $reason && (int) $rejected['reviewed_by'] === $users['admin'] && $rejected['reviewed_at'] !== null, 'rejection restores unpaid booking and records reviewer/reason/time');
    $adminSummary = $request($admin, 'GET', '/admin/payments/show?id=' . $reject['payment']);
    $customerSummary = $request($customer, 'GET', '/bookings/show?id=' . $reject['booking']);
    $assert(str_contains($adminSummary['body'], '&lt;script&gt;Not a valid receipt&lt;/script&gt;') && str_contains($customerSummary['body'], '&lt;script&gt;Not a valid receipt&lt;/script&gt;'), 'rejection reason is escaped for both admin and owner');
    foreach (['verify','reject'] as $action) $assert($request($admin, 'POST', '/admin/payments/' . $action . '?id=' . $reject['payment'], ['_token' => $csrf])['status'] === 409, 'rejected payment cannot transition again: ' . $action);
    $assert($request($customer, 'GET', '/bookings/payment?booking_id=' . $reject['booking'])['status'] === 200, 'rejected booking allows new payment submission');
    $paymentFields = ['_token' => $token($customerSummary), 'method' => 'cash', 'amount' => '1000.25', 'transaction_reference' => 'RETRY-' . $suffix, 'payment_date' => gmdate('Y-m-d')];
    $assert($request($customer, 'POST', '/bookings/payment?booking_id=' . $reject['booking'], $paymentFields)['status'] === 303 && count((new Payment())->forBooking($reject['booking'])) === 2, 'resubmission creates a new pending payment preserving rejected history');
    $assert($request($admin, 'POST', '/admin/payments/verify?id=' . $reject['payment'], ['_token' => $csrf])['status'] === 409, 'old rejected payment cannot approve resubmitted booking');
    $assert($request($admin, 'POST', '/admin/payments/reject?id=' . $optional['payment'], ['_token' => $csrf])['status'] === 303 && (new Payment())->findForAdmin($optional['payment'])['review_notes'] === null, 'rejection without reason succeeds');
    foreach ([str_repeat('X',1001), "bad\x00reason", ['array']] as $invalidReason) $assert($request($admin, 'POST', '/admin/payments/reject?id=' . $invalid['payment'], ['_token' => $csrf, 'reason' => $invalidReason])['status'] === 422, 'invalid rejection reason blocked');
    foreach (['confirmed','cancelled','expired','pending'] as $status) {
        $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status, $invalid['booking']]);
        foreach (['verify','reject'] as $action) $assert($request($admin, 'POST', '/admin/payments/' . $action . '?id=' . $invalid['payment'], ['_token' => $csrf])['status'] === 409, 'invalid booking state blocks ' . $action . ': ' . $status);
    }
    $db->prepare("UPDATE bookings SET status = 'payment_submitted' WHERE id = ?")->execute([$invalid['booking']]);
    foreach (['cancelled','completed'] as $status) {
        $db->prepare('UPDATE flights SET status = ? WHERE id = ?')->execute([$status, $flightId]);
        $assert($request($admin, 'POST', '/admin/payments/verify?id=' . $invalid['payment'], ['_token' => $csrf])['status'] === 409, 'invalid flight blocks confirmation: ' . $status);
    }
    $db->prepare("UPDATE flights SET status = 'scheduled', departure_at = '2000-01-01 10:00:00' WHERE id = ?")->execute([$flightId]);
    $assert($request($admin, 'POST', '/admin/payments/verify?id=' . $invalid['payment'], ['_token' => $csrf])['status'] === 409, 'past flight cannot be confirmed');
    $db->prepare('UPDATE flights SET departure_at = ? WHERE id = ?')->execute([$day . ' 10:00:00', $flightId]);
    $db->prepare("UPDATE bookings SET expires_at = '2000-01-01' WHERE id = ?")->execute([$invalid['booking']]);
    $assert($request($admin, 'POST', '/admin/payments/verify?id=' . $invalid['payment'], ['_token' => $csrf])['status'] === 409, 'expired deadline cannot be confirmed');
    $db->prepare('UPDATE bookings SET expires_at = NULL WHERE id = ?')->execute([$invalid['booking']]);
    foreach (['verified' => $verify, 'rejected' => $optional] as $status => $fixture) {
        $filtered = $request($admin, 'GET', '/admin/payments?status=' . $status);
        $pnr = (new Payment())->findForAdmin($fixture['payment'])['booking_reference'];
        $pendingPnr = (new Payment())->findForAdmin($invalid['payment'])['booking_reference'];
        $assert($filtered['status'] === 200 && str_contains($filtered['body'], $pnr) && !str_contains($filtered['body'], $pendingPnr), 'status filter returns only ' . $status . ' payments');
    }
    $db->exec("CREATE TRIGGER $trigger BEFORE UPDATE ON bookings FOR EACH ROW BEGIN IF NEW.id = {$failure['booking']} AND NEW.status IN ('confirmed','pending') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test review booking update failure'; END IF; END"); $triggerCreated = true;
    foreach (['verify','reject'] as $action) {
        $response = $request($admin, 'POST', '/admin/payments/' . $action . '?id=' . $failure['payment'], ['_token' => $csrf]);
        $unchanged = (new Payment())->findForAdmin($failure['payment']);
        $assert($response['status'] === 503 && !str_contains($response['body'], BASE_PATH) && $unchanged['status'] === 'pending' && $unchanged['booking_status'] === 'payment_submitted' && $unchanged['reviewed_at'] === null, 'failed booking update rolls back payment/audit for ' . $action);
    }
    $db->exec("DROP TRIGGER $trigger"); $triggerCreated = false;
    try { (new Payment())->review($invalid['payment'], $users['customer'], 'verified'); throw new RuntimeException('Non-admin model review succeeded.'); }
    catch (DomainException) { $assert(true, 'model also requires active admin reviewer'); }
    $db->beginTransaction(); $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE')->execute([$flightId]);
    foreach (['verified','rejected'] as $decision) {
        $pipes = []; $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $race['payment'], (string) $users['admin'], $decision], [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Race process failed.');
        fclose($pipes[0]); $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        if (trim((string) fgets($pipes[1])) !== 'READY') throw new RuntimeException('Race worker not ready.');
    }
    $db->commit(); $results = [];
    foreach ($workers as $worker) {
        $results[] = trim(stream_get_contents($worker['stdout'])); $errors = stream_get_contents($worker['stderr']); fclose($worker['stdout']); fclose($worker['stderr']);
        if (proc_close($worker['process']) !== 0 || $errors !== '') throw new RuntimeException('Race worker failed: ' . $errors);
    }
    $workers = []; sort($results); $raced = (new Payment())->findForAdmin($race['payment']);
    $assert($results === ['BLOCKED','REVIEWED'] && $raced['booking_status'] === ($raced['status'] === 'verified' ? 'confirmed' : 'pending'), 'concurrent verify/reject gives one winner and consistent final states');
    $query = $db->prepare('SELECT COUNT(*) FROM tickets WHERE booking_seat_id IN (SELECT id FROM booking_seats WHERE booking_id = ?)'); $query->execute([$verify['booking']]);
    $assert((int) $query->fetchColumn() === 0, 'verification creates no ticket');
    $query = $db->prepare('SELECT status FROM booking_seats WHERE booking_id = ?'); $query->execute([$verify['booking']]);
    $assert($query->fetchColumn() === 'reserved' && is_file($receiptPath), 'review preserves seat allocation and receipt');
    echo "$checks Module 9 checks passed." . PHP_EOL;
} catch (Throwable $exception) { $failed = true; fwrite(STDERR, 'Payment review test failed: ' . $exception->getMessage() . PHP_EOL); }
finally {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    foreach ($workers as $worker) if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); }
    if ($db instanceof PDO) {
        if ($triggerCreated) $db->exec("DROP TRIGGER $trigger");
        foreach ($bookingIds as $id) { foreach (['payments','booking_seats','passengers'] as $table) $db->prepare("DELETE FROM $table WHERE booking_id = ?")->execute([$id]); $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]); }
        foreach ($flightIds as $id) $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$id]);
        foreach ($aircraftIds as $id) { $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$id]); $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$id]); }
        foreach ($airportIds as $id) $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]);
        foreach ($emails as $email) $db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    }
    (new PaymentReceipt())->remove($receiptRelative);
    foreach ($clients as $handle) curl_close($handle);
    echo 'Unique Module 9 fixtures removed.' . PHP_EOL;
}
exit($failed ? 1 : 0);
