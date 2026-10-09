<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
use App\Core\PaymentReceipt;
use App\Models\Payment;
use App\Models\BookingSeat;

if (($argv[1] ?? '') === '--worker') {
    try {
        $db = Database::connection();
        $query = $db->prepare("SELECT * FROM bookings WHERE id = ? AND booking_reference LIKE 'M8-RACE-%'");
        $query->execute([(int) $argv[2]]); $booking = $query->fetch();
        if (!$booking) throw new RuntimeException('Race fixture not found.');
        echo 'READY' . PHP_EOL; flush();
        try {
            (new Payment())->submit((int) $booking['id'], (int) $booking['user_id'], ['method' => 'bank_transfer', 'amount' => '1000.25', 'transaction_reference' => 'RACE-TEST', 'payment_date' => gmdate('Y-m-d')], null);
            echo 'SUBMITTED' . PHP_EOL;
        } catch (DomainException) { echo 'BLOCKED' . PHP_EOL; }
        exit(0);
    } catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . PHP_EOL); exit(1); }
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/');
$url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || !in_array($url['host'] ?? '', ['127.0.0.1', 'localhost'], true)
    || isset($url['user']) || isset($url['pass']) || !empty($url['path']) || isset($url['query']) || isset($url['fragment'])) {
    fwrite(STDERR, 'Use a local development server URL.' . PHP_EOL); exit(1);
}
if (!extension_loaded('curl') || !extension_loaded('gd') || !extension_loaded('fileinfo')) {
    fwrite(STDERR, 'Tests require curl, GD, and fileinfo.' . PHP_EOL); exit(1);
}
$db = null; $failed = false; $checks = 0;
$clients = $bookingIds = $aircraftIds = $airportIds = $flightIds = $workers = $receiptPaths = $mediaPaths = [];
$suffix = bin2hex(random_bytes(5)); $trigger = 'test_payment_' . $suffix; $triggerCreated = false;
$emails = ['customer' => "payment-customer-$suffix@example.invalid", 'other' => "payment-other-$suffix@example.invalid", 'admin' => "payment-admin-$suffix@example.invalid"];
$password = 'Payment-' . bin2hex(random_bytes(12));
$mediaDirectory = sys_get_temp_dir() . '/skyreserve-payment-' . $suffix;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    ++$checks; echo 'PASS: ' . $message . PHP_EOL;
};
$client = static function () use (&$clients): CurlHandle {
    $handle = curl_init();
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]);
    $clients[] = $handle; return $handle;
};
$request = static function (CurlHandle $handle, string $method, string $path, array $fields = []) use ($base): array {
    $headers = []; curl_setopt($handle, CURLOPT_HTTPGET, true);
    if ($method === 'POST') {
        $multipart = (bool) array_filter($fields, static fn ($value): bool => $value instanceof CURLFile);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query($fields));
    }
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
$storedFiles = static fn (): array => glob(BASE_PATH . '/storage/payment_receipts/*.{jpg,png,webp}', GLOB_BRACE) ?: [];
try {
    $db = Database::connection(); $users = [];
    foreach ($emails as $role => $email) {
        $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['Payment tester', $email, password_hash($password, PASSWORD_DEFAULT), $role === 'admin' ? 'admin' : 'customer']);
        $users[$role] = (int) $db->lastInsertId();
    }
    foreach (['Origin', 'Destination'] as $city) {
        do { $code = chr(random_int(65,90)) . chr(random_int(65,90)) . chr(random_int(65,90)); $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $query->execute([$code]); } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code, $city, $city]); $airportIds[] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 4)')->execute(['P-' . $suffix, 'Payment aircraft']); $aircraftIds[] = $aircraftId = (int) $db->lastInsertId();
    foreach (['1A','1B','1C','1D'] as $seatNumber) { $db->prepare('INSERT INTO seats (aircraft_id, seat_number) VALUES (?, ?)')->execute([$aircraftId, $seatNumber]); }
    $day = gmdate('Y-m-d', time() + 172800);
    $db->prepare('INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare) VALUES (?, ?, ?, ?, ?, ?, 1000.25)')->execute(['P' . strtoupper($suffix), $aircraftId, $airportIds[0], $airportIds[1], $day . ' 10:00:00', $day . ' 12:00:00']);
    $flightIds[] = $flightId = (int) $db->lastInsertId();
    $makeBooking = static function (string $tag) use ($db, $users, $flightId, $suffix, &$bookingIds): int {
        $tag = substr($tag, 0, 6);
        $db->prepare('INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount) VALUES (?, ?, ?, 1000.25)')->execute(['M8-' . $tag . '-' . $suffix, $users['customer'], $flightId]);
        $id = (int) $db->lastInsertId(); $bookingIds[] = $id;
        $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name, full_name) VALUES (?, 'Payment', 'Passenger', 'Payment Passenger')")->execute([$id]);
        return $id;
    };
    $guest = $client(); $customer = $client(); $other = $client(); $admin = $client();
    foreach ([[$customer,'customer','/login'], [$other,'other','/login'], [$admin,'admin','/login']] as [$handle,$role,$login]) {
        $form = $request($handle, 'GET', $login);
        $assert($request($handle, 'POST', $login, ['_token' => $token($form), 'email' => $emails[$role], 'password' => $password])['status'] === 303, 'fixture ' . $role . ' login');
    }
    $bookingId = $makeBooking('NOFILE'); $path = '/bookings/payment?booking_id=' . $bookingId;
    $form = $request($customer, 'GET', $path); $csrf = $token($form);
    $fields = ['_token' => $csrf, 'method' => 'bank_transfer', 'amount' => '1000.25', 'transaction_reference' => 'REF-' . $suffix, 'payment_date' => gmdate('Y-m-d')];
    $assert($form['status'] === 200 && str_contains($form['body'], '1000.25') && str_contains($form['body'], 'Airline payment instructions'), 'payment page shows PNR, amount due, and instructions');
    foreach (['GET','POST'] as $method) {
        $assert($request($guest, $method, $path, $fields)['status'] === 303, 'guest blocked: ' . $method);
        $assert($request($admin, $method, $path, $fields)['status'] === 403, 'admin blocked from customer payment: ' . $method);
        $otherFields = $fields; $otherFields['_token'] = $token($request($other, 'GET', '/profile'));
        $assert($request($other, $method, $path, $otherFields)['status'] === 404, 'other customer blocked: ' . $method);
    }
    $invalid = $fields; unset($invalid['_token']);
    $assert($request($customer, 'POST', $path, $invalid)['status'] === 403, 'CSRF required');
    $assert($request($customer, 'GET', '/bookings/payment?booking_id=0')['status'] === 404, 'invalid booking ID blocked');
    $assert($request($customer, 'POST', '/bookings/payment?booking_id=2147483647', $fields)['status'] === 404, 'missing booking blocked');
    foreach (['method' => ['', 'card'], 'amount' => ['', '0', '-1', '1.001', '1e3', '10000000000'], 'transaction_reference' => ['', '<script>alert(1)</script>', str_repeat('X',121)], 'payment_date' => ['', '2026-02-30', gmdate('Y-m-d', time() + 86400)]] as $key => $values) {
        foreach ($values as $value) { $invalid = $fields; $invalid[$key] = $value; $assert($request($customer, 'POST', $path, $invalid)['status'] === 422, 'invalid field rejected: ' . $key . '=' . $value); }
    }
    foreach (['cancelled','expired','confirmed','payment_submitted'] as $status) {
        $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status, $bookingId]);
        $assert($request($customer, 'POST', $path, $fields)['status'] === 409, 'booking state blocks submission: ' . $status);
    }
    $db->prepare("UPDATE bookings SET status = 'pending', expires_at = '2000-01-01' WHERE id = ?")->execute([$bookingId]);
    $assert($request($customer, 'GET', $path)['status'] === 409, 'expired deadline blocked');
    $db->prepare('UPDATE bookings SET expires_at = NULL WHERE id = ?')->execute([$bookingId]);
    foreach (['cancelled','completed'] as $status) {
        $db->prepare('UPDATE flights SET status = ? WHERE id = ?')->execute([$status, $flightId]);
        $assert($request($customer, 'POST', $path, $fields)['status'] === 409, 'flight state blocks payment: ' . $status);
    }
    $db->prepare("UPDATE flights SET status = 'scheduled', departure_at = '2000-01-01 10:00:00' WHERE id = ?")->execute([$flightId]);
    $assert($request($customer, 'GET', $path)['status'] === 409, 'past flight blocked');
    $db->prepare('UPDATE flights SET departure_at = ? WHERE id = ?')->execute([$day . ' 10:00:00', $flightId]);
    $assert($request($customer, 'POST', $path, $fields + ['currency' => 'USD', 'status' => 'verified', 'user_id' => $users['other']])['status'] === 303, 'submission without receipt succeeds');
    $payment = (new Payment())->forBooking($bookingId)[0];
    $assert($payment['status'] === 'pending' && $payment['currency'] === 'PKR' && $payment['proof_path'] === null && $payment['reviewed_by'] === null, 'pending payment uses booking currency, no receipt or review');
    $query = $db->prepare('SELECT status FROM bookings WHERE id = ?'); $query->execute([$bookingId]);
    $assert($query->fetchColumn() === 'payment_submitted', 'booking awaits verification without confirmation');
    $assert($request($customer, 'POST', $path, $fields)['status'] === 409, 'duplicate submission blocked');
    $summary = $request($customer, 'GET', '/bookings/show?id=' . $bookingId);
    $assert($summary['status'] === 200 && str_contains($summary['body'], 'Awaiting Verification') && str_contains($summary['body'], 'Bank transfer') && str_contains($summary['body'], $fields['transaction_reference']) && str_contains($summary['body'], 'No receipt uploaded') && str_contains($summary['body'], $fields['payment_date']), 'summary displays pending payment details');
    $assert($request($customer, 'GET', '/bookings/seats?booking_id=' . $bookingId)['status'] === 200, 'existing seat map stays available while verification is pending');
    $query = $db->prepare('SELECT id FROM passengers WHERE booking_id = ?'); $query->execute([$bookingId]); $passengerId = (int) $query->fetchColumn();
    $query = $db->prepare("SELECT id FROM seats WHERE aircraft_id = ? AND seat_number = '1A'"); $query->execute([$aircraftId]);
    (new BookingSeat())->assign($bookingId, $users['customer'], $passengerId, (int) $query->fetchColumn());
    $assigned = (new BookingSeat())->forBooking($bookingId);
    $assert(count($assigned) === 1 && $assigned[0]['status'] === 'reserved', 'seat selection works after payment submission without confirming the seat');
    try { $db->prepare("INSERT INTO payments (booking_id, amount, status) VALUES (?, 1, 'pending')")->execute([$bookingId]); throw new RuntimeException('Active duplicate constraint failed.'); }
    catch (PDOException $exception) { $assert((int) $exception->errorInfo[1] === 1062, 'database rejects duplicate active payment'); }
    mkdir($mediaDirectory, 0700);
    $image = imagecreatetruecolor(16, 16); imagefill($image, 0, 0, imagecolorallocate($image, 28, 118, 143));
    $images = [];
    foreach (['jpg','jpeg','png','webp'] as $extension) {
        $mediaPaths[] = $images[$extension] = $mediaDirectory . '/valid.' . $extension;
        $written = match ($extension) { 'jpg','jpeg' => imagejpeg($image, $images[$extension]), 'png' => imagepng($image, $images[$extension]), 'webp' => imagewebp($image, $images[$extension]) };
        if (!$written) throw new RuntimeException('Image fixture failed.');
    }
    imagedestroy($image);
    foreach ($images as $extension => $imagePath) {
        $uploadBooking = $makeBooking(strtoupper($extension));
        $uploadFields = $fields; $uploadFields['receipt'] = new CURLFile($imagePath, 'text/plain', 'customer-original.' . $extension);
        $response = $request($customer, 'POST', '/bookings/payment?booking_id=' . $uploadBooking, $uploadFields);
        $assert($response['status'] === 303, 'valid ' . $extension . ' succeeds despite untrusted browser MIME');
        $uploaded = (new Payment())->forBooking($uploadBooking)[0]; $receiptPaths[] = $uploaded['proof_path'];
        $assert((bool) preg_match('/\Apayment_receipts\/[a-f0-9]{48}\.(jpg|png|webp)\z/', $uploaded['proof_path']), 'receipt uses random relative path: ' . $extension);
        $receipt = $request($customer, 'GET', '/payments/receipt?id=' . $uploaded['id']);
        $assert($receipt['status'] === 200 && @getimagesizefromstring($receipt['body']) !== false && $receipt['headers']['x-content-type-options'] === 'nosniff' && str_contains($receipt['headers']['cache-control'], 'no-store'), 'owner receives safe private image: ' . $extension);
        $assert($request($other, 'GET', '/payments/receipt?id=' . $uploaded['id'])['status'] === 404, 'other customer cannot view receipt: ' . $extension);
        $assert($request($guest, 'GET', '/payments/receipt?id=' . $uploaded['id'])['status'] === 303, 'guest cannot view receipt: ' . $extension);
        $assert($request($admin, 'GET', '/payments/receipt?id=' . $uploaded['id'])['status'] === 403, 'customer receipt has no admin verification access: ' . $extension);
        $head = $request($customer, 'HEAD', '/payments/receipt?id=' . $uploaded['id']);
        $assert($head['status'] === 200 && $head['body'] === '' && (int) $head['headers']['content-length'] > 0, 'authorized HEAD has headers only: ' . $extension);
        $assert($request($other, 'HEAD', '/payments/receipt?id=' . $uploaded['id'])['status'] === 404, 'HEAD also enforces ownership: ' . $extension);
        $assert($request($customer, 'GET', '/storage/' . $uploaded['proof_path'])['status'] === 404, 'receipt cannot be accessed as a public file: ' . $extension);
        $summary = $request($customer, 'GET', '/bookings/show?id=' . $uploadBooking);
        $assert(str_contains($summary['body'], '/payments/receipt?id=' . $uploaded['id']) && !str_contains($summary['body'], $uploaded['proof_path']), 'summary shows protected receipt link without storage path: ' . $extension);
    }
    // A real 3 MB PNG ensures the server accepts receipts beyond PHP's common 2 MB default.
    $chunk = static function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    };
    // Random compressed bytes can accidentally contain <?= and trigger the script guard.
    // This acceptance fixture tests size; intentionally unsafe uploads are tested below.
    for ($attempt = 0; $attempt < 20; ++$attempt) {
        $rows = '';
        for ($row = 0; $row < 1024; ++$row) $rows .= "\x00" . random_bytes(3072);
        $largeBytes = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 1024, 1024, 8, 2, 0, 0, 0)) . $chunk('IDAT', gzcompress($rows, 1)) . $chunk('IEND', '');
        if (!preg_match('/<\?(?:php|=)|<script\b/i', $largeBytes)) break;
    }
    if (preg_match('/<\?(?:php|=)|<script\b/i', $largeBytes)) throw new RuntimeException('Unable to create a marker-free large image fixture.');
    $mediaPaths[] = $largeValid = $mediaDirectory . '/large-valid.png';
    file_put_contents($largeValid, $largeBytes);
    unset($rows, $largeBytes);
    $largeBooking = $makeBooking('LARGE'); $largeFields = $fields; $largeFields['receipt'] = new CURLFile($largeValid, 'image/png', 'receipt.png');
    $assert(filesize($largeValid) > 2 * 1024 * 1024 && filesize($largeValid) < PaymentReceipt::MAX_BYTES && $request($customer, 'POST', '/bookings/payment?booking_id=' . $largeBooking, $largeFields)['status'] === 303, 'valid receipt between 2 MB and 5 MB uploads successfully');
    $invalidBooking = $makeBooking('INVALID'); $invalidPath = '/bookings/payment?booking_id=' . $invalidBooking;
    $mediaPaths[] = $scriptFile = $mediaDirectory . '/script.php'; file_put_contents($scriptFile, '<?php echo "unsafe"; ?>');
    $mediaPaths[] = $oversized = $mediaDirectory . '/oversized.png'; file_put_contents($oversized, str_repeat('X', PaymentReceipt::MAX_BYTES + 1));
    $mediaPaths[] = $corrupt = $mediaDirectory . '/corrupt.png'; file_put_contents($corrupt, substr(file_get_contents($images['png']), 0, 40));
    $mediaPaths[] = $polyglot = $mediaDirectory . '/polyglot.png'; file_put_contents($polyglot, file_get_contents($images['png']) . '<?php echo "unsafe"; ?>');
    $filesBefore = $storedFiles();
    foreach ([[$images['png'],'receipt.php'], [$images['png'],'receipt.jpg'], [$scriptFile,'receipt.png'], [$oversized,'receipt.png'], [$corrupt,'receipt.png'], [$polyglot,'receipt.png']] as [$mediaPath,$filename]) {
        $invalid = $fields; $invalid['receipt'] = new CURLFile($mediaPath, 'image/png', $filename);
        $assert($request($customer, 'POST', $invalidPath, $invalid)['status'] === 422, 'invalid extension/MIME/size/corrupt/script upload rejected: ' . basename($mediaPath) . '/' . $filename);
    }
    $assert($storedFiles() === $filesBefore && !(new Payment())->forBooking($invalidBooking), 'invalid uploads create no stored files or payments');
    $failureBooking = $makeBooking('DBFAIL');
    $db->exec("CREATE TRIGGER $trigger BEFORE INSERT ON payments FOR EACH ROW BEGIN IF NEW.booking_id = $failureBooking THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test payment insert failure'; END IF; END"); $triggerCreated = true;
    $failureFields = $fields; $failureFields['receipt'] = new CURLFile($images['png'], 'image/png', 'receipt.png');
    $failedSave = $request($customer, 'POST', '/bookings/payment?booking_id=' . $failureBooking, $failureFields);
    $assert($failedSave['status'] === 503 && str_contains($failedSave['body'], 'could not be saved') && !str_contains($failedSave['body'], BASE_PATH), 'failed DB save returns safe error');
    $assert($storedFiles() === $filesBefore && !(new Payment())->forBooking($failureBooking), 'failed DB save cleans up uploaded image and payment');
    $query = $db->prepare('SELECT status FROM bookings WHERE id = ?'); $query->execute([$failureBooking]);
    $assert($query->fetchColumn() === 'pending', 'failed payment save rolls back booking status');
    $db->exec("DROP TRIGGER $trigger"); $triggerCreated = false;
    $db->exec("CREATE TRIGGER $trigger BEFORE UPDATE ON bookings FOR EACH ROW BEGIN IF NEW.id = $failureBooking AND NEW.status = 'payment_submitted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test booking update failure'; END IF; END"); $triggerCreated = true;
    $failedUpdate = $request($customer, 'POST', '/bookings/payment?booking_id=' . $failureBooking, $failureFields);
    $assert($failedUpdate['status'] === 503 && $storedFiles() === $filesBefore && !(new Payment())->forBooking($failureBooking), 'booking update failure rolls back inserted payment and removes receipt');
    $db->exec("DROP TRIGGER $trigger"); $triggerCreated = false;
    $raceBooking = $makeBooking('RACE');
    $db->beginTransaction(); $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE')->execute([$flightId]);
    for ($index = 0; $index < 2; $index++) {
        $pipes = []; $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $raceBooking], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Race process failed.');
        fclose($pipes[0]); $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        if (trim((string) fgets($pipes[1])) !== 'READY') throw new RuntimeException('Race worker not ready.');
    }
    $db->commit(); $results = [];
    foreach ($workers as $worker) {
        $results[] = trim(stream_get_contents($worker['stdout'])); $errors = stream_get_contents($worker['stderr']); fclose($worker['stdout']); fclose($worker['stderr']);
        if (proc_close($worker['process']) !== 0 || $errors !== '') throw new RuntimeException('Race worker failed: ' . $errors);
    }
    $workers = []; sort($results);
    $assert($results === ['BLOCKED','SUBMITTED'] && count((new Payment())->forBooking($raceBooking)) === 1, 'concurrent payment submissions produce exactly one payment');
    $query = $db->prepare('SELECT COUNT(*) FROM tickets WHERE booking_seat_id IN (SELECT id FROM booking_seats WHERE booking_id IN (' . implode(',', array_fill(0, count($bookingIds), '?')) . '))'); $query->execute($bookingIds);
    $assert((int) $query->fetchColumn() === 0, 'payment submission generates no tickets');
    echo "$checks Module 8 checks passed." . PHP_EOL;
} catch (Throwable $exception) { $failed = true; fwrite(STDERR, 'Payment test failed: ' . $exception->getMessage() . PHP_EOL); }
finally {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    foreach ($workers as $worker) { if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); } }
    if ($db instanceof PDO) {
        if ($triggerCreated) $db->exec("DROP TRIGGER $trigger");
        foreach ($bookingIds as $id) {
            foreach ((new Payment())->forBooking($id) as $payment) (new PaymentReceipt())->remove($payment['proof_path']);
            foreach (['payments','booking_seats','passengers'] as $table) $db->prepare("DELETE FROM $table WHERE booking_id = ?")->execute([$id]);
            $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
        }
        foreach ($flightIds as $id) $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$id]);
        foreach ($aircraftIds as $id) { $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$id]); $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$id]); }
        foreach ($airportIds as $id) $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]);
        foreach ($emails as $email) $db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    }
    foreach ($mediaPaths as $mediaPath) { if (is_file($mediaPath) && dirname($mediaPath) === $mediaDirectory) unlink($mediaPath); }
    if (is_dir($mediaDirectory)) rmdir($mediaDirectory);
    foreach ($clients as $handle) curl_close($handle);
    echo 'Unique Module 8 fixtures removed.' . PHP_EOL;
}
exit($failed ? 1 : 0);
