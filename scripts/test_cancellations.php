<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
use App\Models\Booking;
use App\Models\Cancellation;
use App\Models\Ticket;

if (($argv[1] ?? '') === '--worker') {
    try {
        $db = Database::connection();
        $query = $db->prepare("SELECT id FROM bookings WHERE id = ? AND booking_reference LIKE 'M11-RACE-%'"); $query->execute([(int) $argv[3]]);
        if (!$query->fetchColumn()) throw new RuntimeException('Race fixture not found.');
        echo 'READY' . PHP_EOL; flush();
        try {
            if ($argv[2] === 'request') (new Cancellation())->request((int) $argv[3], (int) $argv[4], 'Race request');
            else (new Cancellation())->review((int) $argv[5], (int) $argv[4], $argv[2]);
            echo 'SAVED' . PHP_EOL;
        } catch (DomainException) { echo 'BLOCKED' . PHP_EOL; }
        exit(0);
    } catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage() . PHP_EOL); exit(1); }
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000', '/'); $url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || !in_array($url['host'] ?? '', ['localhost','127.0.0.1'], true)
    || isset($url['user']) || isset($url['pass']) || !empty($url['path']) || isset($url['query']) || isset($url['fragment'])) { fwrite(STDERR, 'Use a local development server URL.' . PHP_EOL); exit(1); }
if (!extension_loaded('curl')) { fwrite(STDERR, 'Tests require curl.' . PHP_EOL); exit(1); }
$db = null; $checks = 0; $failed = false; $bookings = $users = $emails = $airports = $clients = $workers = [];
$aircraftId = $flightId = null; $suffix = bin2hex(random_bytes(4)); $password = 'Cancel-' . bin2hex(random_bytes(12)); $trigger = 'test_cancel_' . $suffix; $triggerCreated = false;
$assert = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) throw new RuntimeException($message); ++$checks; echo 'PASS: ' . $message . PHP_EOL; };
$client = static function () use (&$clients): CurlHandle { $handle = curl_init(); curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15]); $clients[] = $handle; return $handle; };
$request = static function (CurlHandle $handle, string $method, string $path, array $fields = []) use ($base): array {
    $headers = []; curl_setopt($handle, CURLOPT_HTTPGET, true);
    if ($method === 'POST') curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt_array($handle, [CURLOPT_URL => $base . $path, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_NOBODY => $method === 'HEAD', CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int { if (str_contains($line, ':')) { [$key,$value] = explode(':',$line,2); $headers[strtolower(trim($key))] = trim($value); } return strlen($line); }]);
    $body = curl_exec($handle); if ($body === false) throw new RuntimeException(curl_error($handle));
    return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body, 'headers' => $headers];
};
$token = static function (array $response): string { if (!preg_match('/name="_token" value="([a-f0-9]{64})"/', $response['body'], $match)) throw new RuntimeException('CSRF token missing.'); return $match[1]; };
try {
    $db = Database::connection();
    foreach (['customer','other','admin'] as $role) { $emails[$role] = "cancel-$role-$suffix@example.invalid"; $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['Cancellation ' . $role,$emails[$role],password_hash($password,PASSWORD_DEFAULT),$role === 'admin' ? 'admin' : 'customer']); $users[$role] = (int) $db->lastInsertId(); }
    foreach (['Origin','Destination'] as $name) {
        do { $code = chr(random_int(65,90)) . chr(random_int(65,90)) . chr(random_int(65,90)); $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $query->execute([$code]); } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code,'Cancel ' . $name,$name]); $airports[] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 3)')->execute(['C-' . $suffix,'Cancellation aircraft']); $aircraftId = (int) $db->lastInsertId(); $seats = [];
    foreach (['1A','2A','3A'] as $seat) { $db->prepare('INSERT INTO seats (aircraft_id, seat_number) VALUES (?, ?)')->execute([$aircraftId,$seat]); $seats[] = (int) $db->lastInsertId(); }
    $day = gmdate('Y-m-d',time() + 172800);
    $db->prepare('INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare) VALUES (?, ?, ?, ?, ?, ?, 1500.25)')->execute(['C' . strtoupper($suffix),$aircraftId,$airports[0],$airports[1],$day . ' 10:00:00',$day . ' 12:00:00']); $flightId = (int) $db->lastInsertId();
    $make = static function (string $tag, string $status = 'pending', ?int $seat = null, string $owner = 'customer') use ($db,$suffix,$flightId,$aircraftId,$users,&$bookings): array {
        $db->prepare('INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount, status) VALUES (?, ?, ?, 1500.25, ?)')->execute(['M11-' . $tag . '-' . $suffix,$users[$owner],$flightId,$status]); $bookingId = (int) $db->lastInsertId(); $bookings[] = $bookingId;
        $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name, full_name, document_number) VALUES (?, 'Cancel', 'Passenger', '<b>Cancel Passenger</b>', 'AB123456')")->execute([$bookingId]); $passenger = (int) $db->lastInsertId(); $allocation = $payment = null;
        if ($seat) { $db->prepare('INSERT INTO booking_seats (booking_id, passenger_id, flight_id, aircraft_id, seat_id) VALUES (?, ?, ?, ?, ?)')->execute([$bookingId,$passenger,$flightId,$aircraftId,$seat]); $allocation = (int) $db->lastInsertId(); }
        if (in_array($status,['confirmed','payment_submitted'],true)) { $db->prepare("INSERT INTO payments (booking_id, amount, method, status) VALUES (?, 1500.25, 'cash', ?)")->execute([$bookingId,$status === 'confirmed' ? 'verified' : 'pending']); $payment = (int) $db->lastInsertId(); }
        return ['booking' => $bookingId,'passenger' => $passenger,'allocation' => $allocation,'payment' => $payment];
    };
    $approve = $make('YES','confirmed',$seats[0]); $reject = $make('NO','confirmed',$seats[1]); $pending = $make('PEND'); $submitted = $make('PAY','payment_submitted'); $invalid = $make('BAD'); $failure = $make('FAIL','confirmed',$seats[2]); $race = $make('RACE'); $otherBooking = $make('OTHER','pending',null,'other');
    $approveTicket = (new Ticket())->generate($approve['booking'], ['id' => $users['customer']])[0];
    $rejectTicket = (new Ticket())->generate($reject['booking'], ['id' => $users['customer']])[0];
    $failureTicket = (new Ticket())->generate($failure['booking'], ['id' => $users['customer']])[0];
    $guest = $client(); $customer = $client(); $other = $client(); $admin = $client();
    foreach ([[$customer,'customer','/login'],[$other,'other','/login'],[$admin,'admin','/login']] as [$handle,$role,$login]) { $form = $request($handle,'GET',$login); $assert($request($handle,'POST',$login,['_token' => $token($form),'email' => $emails[$role],'password' => $password])['status'] === 303,'fixture ' . $role . ' login'); }
    $csrf = $token($request($customer,'GET','/profile')); $otherCsrf = $token($request($other,'GET','/profile')); $adminCsrf = $token($request($admin,'GET','/admin'));
    $list = $request($customer,'GET','/bookings');
    $assert($list['status'] === 200 && str_contains($list['body'],'M11-YES-' . $suffix) && !str_contains($list['body'],'M11-OTHER-' . $suffix),'My Bookings contains only current customer records');
    foreach (['&lt;b&gt;Cancel Passenger&lt;/b&gt;','1A / Economy','1500.25','Verified','/tickets/show?id=' . $approveTicket,$day . ' 10:00:00'] as $content) $assert(str_contains($list['body'],$content),'My Bookings content: ' . $content);
    $assert($request($guest,'GET','/bookings')['status'] === 303 && $request($admin,'GET','/bookings')['status'] === 403,'My Bookings role protected');
    $filtered = $request($customer,'GET','/bookings?status=confirmed');
    $assert(str_contains($filtered['body'],'M11-YES-' . $suffix) && !str_contains($filtered['body'],'M11-PEND-' . $suffix),'customer status filter selects matching bookings');
    $assert(str_contains($request($customer,'GET','/bookings?status=cancelled')['body'],'No bookings match this filter'),'empty filter has useful empty state');
    foreach (['/bookings?status=bad','/bookings?status[]=pending'] as $path) $assert($request($customer,'GET',$path)['status'] === 422,'invalid booking filter blocked');
    $path = '/bookings/cancel?booking_id=' . $approve['booking'];
    foreach (['GET','POST','HEAD'] as $method) {
        $assert($request($guest,$method,$path)['status'] === 303,'guest cancellation blocked: ' . $method);
        $assert($request($other,$method,$path,['_token' => $otherCsrf])['status'] === 404,'other customer cancellation blocked: ' . $method);
        $assert($request($admin,$method,$path,['_token' => $adminCsrf])['status'] === 403,'admin cannot impersonate customer cancellation: ' . $method);
    }
    $form = $request($customer,'GET',$path);
    $assert($form['status'] === 200 && str_contains($form['body'],'data-confirm=') && str_contains($form['body'],'does not initiate a refund'),'request form explains confirmation, review, and manual payments');
    $assert($request($customer,'POST',$path)['status'] === 403,'request requires CSRF');
    foreach ([str_repeat('x',1001),"bad\0reason",['array']] as $reason) $assert($request($customer,'POST',$path,['_token' => $csrf,'reason' => $reason])['status'] === 422,'invalid reason rejected');
    foreach (['/bookings/cancel?booking_id=2147483647','/bookings/cancel?booking_id[]=1'] as $missing) $assert($request($customer,'POST',$missing,['_token' => $csrf])['status'] === 404,'invalid booking ID blocked');
    $reason = '<script>Travel plans changed</script>';
    $assert($request($customer,'POST',$path,['_token' => $csrf,'reason' => $reason,'previous_booking_status' => 'pending','requested_by' => $users['other']])['status'] === 303,'customer cancellation request succeeds');
    $approvedRequest = (new Cancellation())->forBooking($approve['booking'])[0];
    $assert($approvedRequest['status'] === 'pending' && $approvedRequest['previous_booking_status'] === 'confirmed' && (int) $approvedRequest['requested_by'] === $users['customer'] && (new Booking())->findForCustomer($approve['booking'],$users['customer'])['status'] === 'cancellation_requested','request stores true owner and previous status and updates booking');
    $summary = $request($customer,'GET','/bookings/show?id=' . $approve['booking']);
    $assert(str_contains($summary['body'],'Cancellation Requested') && str_contains($summary['body'],'&lt;script&gt;Travel plans changed&lt;/script&gt;'),'summary shows request and escapes customer reason');
    $assert($request($customer,'POST',$path,['_token' => $csrf])['status'] === 409 && count((new Cancellation())->forBooking($approve['booking'])) === 1,'duplicate request blocked');
    try { $db->prepare('INSERT INTO cancellations (booking_id, requested_by, previous_booking_status) VALUES (?, ?, ?)')->execute([$approve['booking'],$users['customer'],'confirmed']); throw new RuntimeException('Duplicate pending constraint failed.'); }
    catch (PDOException $exception) { $assert($exception->getCode() === '23000','database blocks duplicate pending cancellation'); }
    $query = $db->prepare('SELECT status FROM booking_seats WHERE id = ?'); $query->execute([$approve['allocation']]);
    $assert($query->fetchColumn() === 'reserved' && (new Ticket())->findAuthorized($approveTicket,['id' => $users['customer']])['status'] === 'valid','request keeps seats held and ticket valid pending review');
    $assert($request($customer,'POST','/bookings/tickets?booking_id=' . $approve['booking'],['_token' => $csrf])['status'] === 409,'ticket issuance blocked during cancellation review');
    $assert($request($customer,'POST','/bookings/payment?booking_id=' . $approve['booking'],['_token' => $csrf])['status'] === 409,'payment submission blocked during cancellation review');
    $adminDetail = '/admin/cancellations/show?id=' . $approvedRequest['id'];
    foreach ([['GET','/admin/cancellations'],['GET',$adminDetail],['HEAD',$adminDetail],['POST','/admin/cancellations/approve?id=' . $approvedRequest['id']],['POST','/admin/cancellations/reject?id=' . $approvedRequest['id']]] as [$method,$adminPath]) {
        $assert($request($guest,$method,$adminPath)['status'] === 303,'guest review blocked: ' . $method . ' ' . $adminPath);
        $assert($request($customer,$method,$adminPath,['_token' => $csrf])['status'] === 403,'non-admin review blocked: ' . $method . ' ' . $adminPath);
    }
    $assert(str_contains($request($admin,'GET','/admin/cancellations')['body'],'M11-YES-' . $suffix),'admin pending list shows request');
    $detail = $request($admin,'GET',$adminDetail);
    foreach ([$emails['customer'],'M11-YES-' . $suffix,'&lt;script&gt;Travel plans changed&lt;/script&gt;','/admin/tickets/show?id=' . $approveTicket,'/admin/payments/show?id=' . $approve['payment']] as $content) $assert(str_contains($detail['body'],$content),'admin details show linked data safely: ' . $content);
    foreach (['approve','reject'] as $action) {
        $reviewPath = '/admin/cancellations/' . $action . '?id=' . $approvedRequest['id'];
        $assert($request($admin,'POST',$reviewPath)['status'] === 403,'review CSRF required: ' . $action);
        $assert($request($admin,'GET',$reviewPath)['status'] === 405,'review POST required: ' . $action);
    }
    $assert($request($admin,'GET','/admin/cancellations?status[]=bad')['status'] === 422,'invalid admin filter rejected');
    $assert($request($admin,'GET','/admin/cancellations/show?id=2147483647')['status'] === 404,'missing admin request rejected');
    foreach (['approve','reject'] as $action) $assert($request($admin,'POST','/admin/cancellations/' . $action . '?id=2147483647',['_token' => $adminCsrf])['status'] === 404,'missing review request rejected: ' . $action);
    $assert($request($admin,'POST','/admin/cancellations/approve?id=' . $approvedRequest['id'],['_token' => $adminCsrf,'note' => ['array']])['status'] === 422,'invalid admin note rejected');
    $paymentBefore = $db->query('SELECT * FROM payments WHERE id = ' . (int) $approve['payment'])->fetch();
    $assert($request($admin,'POST','/admin/cancellations/approve?id=' . $approvedRequest['id'],['_token' => $adminCsrf,'note' => '<b>Approved manually</b>','reviewed_by' => $users['other'],'refund_amount' => '1500.25'])['status'] === 303,'admin approval succeeds');
    $reviewed = (new Cancellation())->findForAdmin((int) $approvedRequest['id']);
    $assert($reviewed['status'] === 'approved' && $reviewed['booking_status'] === 'cancelled' && (int) $reviewed['reviewed_by'] === $users['admin'] && $reviewed['reviewed_at'] !== null && $reviewed['refund_amount'] === '0.00','approval records admin audit and cancelled booking without refund');
    $query->execute([$approve['allocation']]);
    $assert($query->fetchColumn() === 'released' && (new Ticket())->findAuthorized($approveTicket,['id' => $users['customer']])['status'] === 'void','approval releases seat and voids ticket');
    $assert($db->query('SELECT * FROM payments WHERE id = ' . (int) $approve['payment'])->fetch() === $paymentBefore,'approval leaves every payment field unchanged');
    (new \App\Models\BookingSeat())->assign($otherBooking['booking'],$users['other'],$otherBooking['passenger'],$seats[0]);
    $queryReuse = $db->prepare('SELECT booking_id FROM booking_seats WHERE flight_id = ? AND occupied_seat_id = ?'); $queryReuse->execute([$flightId,$seats[0]]);
    $assert((int) $queryReuse->fetchColumn() === $otherBooking['booking'],'approved cancellation makes the seat available to another booking');
    $ticketView = $request($customer,'GET','/tickets/show?id=' . $approveTicket);
    $assert(str_contains($ticketView['body'],'VOID TICKET') && str_contains($ticketView['body'],'Cancelled'),'void ticket is clearly invalid for travel');
    $assert(str_contains($request($customer,'GET','/tickets/download?id=' . $approveTicket)['body'],'VOID TICKET'),'download preserves void warning');
    $assert(str_contains($request($customer,'GET','/bookings/show?id=' . $approve['booking'])['body'],'&lt;b&gt;Approved manually&lt;/b&gt;'),'owner sees escaped admin note');
    foreach (['approve','reject'] as $action) $assert($request($admin,'POST','/admin/cancellations/' . $action . '?id=' . $approvedRequest['id'],['_token' => $adminCsrf])['status'] === 409,'approved request cannot transition again: ' . $action);
    $assert(str_contains($request($admin,'GET','/admin/cancellations?status=approved')['body'],'M11-YES-' . $suffix),'approved filter includes completed review');
    foreach ([[$reject,'confirmed'],[$pending,'pending'],[$submitted,'payment_submitted']] as [$fixture,$previous]) {
        $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $fixture['booking'],['_token' => $csrf])['status'] === 303,'request from ' . $previous . ' succeeds');
        $record = (new Cancellation())->forBooking($fixture['booking'])[0];
        $assert($request($admin,'POST','/admin/cancellations/reject?id=' . $record['id'],['_token' => $adminCsrf,'note' => 'Retain booking'])['status'] === 303,'admin rejection succeeds for ' . $previous);
        $assert((new Booking())->findForCustomer($fixture['booking'],$users['customer'])['status'] === $previous,'rejection restores exact previous status: ' . $previous);
        $assert($request($admin,'POST','/admin/cancellations/approve?id=' . $record['id'],['_token' => $adminCsrf])['status'] === 409,'rejected request cannot transition again');
    }
    $assert((new Ticket())->findAuthorized($rejectTicket,['id' => $users['customer']])['status'] === 'valid','rejection keeps ticket valid');
    $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $reject['booking'],['_token' => $csrf,'reason' => 'New request'])['status'] === 303 && count((new Cancellation())->forBooking($reject['booking'])) === 2,'new request after rejection preserves immutable history');
    $repeat = (new Cancellation())->forBooking($reject['booking'])[0];
    $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $reject['booking'],['_token' => $csrf])['status'] === 409,'duplicate active request still blocked after retry');
    $assert(str_contains($request($admin,'GET','/admin/cancellations?status=rejected')['body'],'M11-PEND-' . $suffix),'rejected filter shows history');
    foreach (['cancelled','expired','cancellation_requested'] as $status) {
        $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status,$invalid['booking']]);
        $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $invalid['booking'],['_token' => $csrf])['status'] === 409,'ineligible booking blocked: ' . $status);
    }
    $db->prepare("UPDATE bookings SET status = 'pending', expires_at = '2000-01-01' WHERE id = ?")->execute([$invalid['booking']]);
    $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $invalid['booking'],['_token' => $csrf])['status'] === 409,'expired payment deadline blocked');
    $db->prepare('UPDATE bookings SET expires_at = NULL WHERE id = ?')->execute([$invalid['booking']]);
    foreach (['cancelled','completed'] as $status) {
        $db->prepare('UPDATE flights SET status = ? WHERE id = ?')->execute([$status,$flightId]);
        $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $invalid['booking'],['_token' => $csrf])['status'] === 409,'invalid flight blocks request: ' . $status);
        $assert($request($admin,'POST','/admin/cancellations/approve?id=' . $repeat['id'],['_token' => $adminCsrf])['status'] === 409,'invalid flight blocks approval: ' . $status);
    }
    $db->prepare("UPDATE flights SET status = 'scheduled', departure_at = '2000-01-01 10:00:00' WHERE id = ?")->execute([$flightId]);
    $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $invalid['booking'],['_token' => $csrf])['status'] === 409,'past flight request blocked');
    $db->prepare('UPDATE flights SET departure_at = ? WHERE id = ?')->execute([$day . ' 10:00:00',$flightId]);
    $db->prepare("UPDATE tickets SET status = 'used' WHERE id = ?")->execute([$failureTicket]);
    $assert($request($customer,'POST','/bookings/cancel?booking_id=' . $failure['booking'],['_token' => $csrf])['status'] === 409,'used ticket request blocked');
    $db->prepare("UPDATE tickets SET status = 'valid' WHERE id = ?")->execute([$failureTicket]);
    $db->exec("CREATE TRIGGER $trigger BEFORE UPDATE ON bookings FOR EACH ROW BEGIN IF NEW.id = {$failure['booking']} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test cancellation update failure'; END IF; END"); $triggerCreated = true;
    $response = $request($customer,'POST','/bookings/cancel?booking_id=' . $failure['booking'],['_token' => $csrf]);
    $assert($response['status'] === 503 && (new Cancellation())->forBooking($failure['booking']) === [] && !str_contains($response['body'],BASE_PATH),'request failure rolls back cancellation insert safely');
    $db->exec("DROP TRIGGER $trigger"); $triggerCreated = false;
    $request($customer,'POST','/bookings/cancel?booking_id=' . $failure['booking'],['_token' => $csrf]); $failedRequest = (new Cancellation())->forBooking($failure['booking'])[0];
    $db->exec("CREATE TRIGGER $trigger BEFORE UPDATE ON bookings FOR EACH ROW BEGIN IF NEW.id = {$failure['booking']} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test cancellation review failure'; END IF; END"); $triggerCreated = true;
    $assert($request($admin,'POST','/admin/cancellations/approve?id=' . $failedRequest['id'],['_token' => $adminCsrf])['status'] === 503,'failed approval returns safe error');
    $query->execute([$failure['allocation']]); $failedRecord = (new Cancellation())->findForAdmin((int) $failedRequest['id']);
    $assert($query->fetchColumn() === 'reserved' && (new Ticket())->findAuthorized($failureTicket,['id' => $users['customer']])['status'] === 'valid' && $failedRecord['status'] === 'pending' && $failedRecord['booking_status'] === 'cancellation_requested' && $failedRecord['reviewed_at'] === null,'failed approval rolls back booking, review audit, seats, and ticket');
    $assert($request($admin,'POST','/admin/cancellations/reject?id=' . $failedRequest['id'],['_token' => $adminCsrf])['status'] === 503 && (new Cancellation())->findForAdmin((int) $failedRequest['id'])['status'] === 'pending','failed rejection rolls back audit and request');
    $db->exec("DROP TRIGGER $trigger"); $triggerCreated = false;
    foreach (['confirmed','pending','cancelled'] as $status) {
        $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$status,$failure['booking']]);
        $assert($request($admin,'POST','/admin/cancellations/reject?id=' . $failedRequest['id'],['_token' => $adminCsrf])['status'] === 409,'invalid review booking state blocked: ' . $status);
    }
    $db->prepare("UPDATE bookings SET status = 'cancellation_requested' WHERE id = ?")->execute([$failure['booking']]);
    try { (new Cancellation())->review((int) $failedRequest['id'],$users['customer'],'approved'); throw new RuntimeException('Non-admin model review allowed.'); }
    catch (DomainException) { $assert(true,'model requires active admin reviewer'); }
    try { (new Cancellation())->request($invalid['booking'],$users['other'],''); throw new RuntimeException('Other owner model request allowed.'); }
    catch (OutOfBoundsException) { $assert(true,'model enforces customer ownership'); }
    foreach (['request','review'] as $phase) {
        $db->beginTransaction(); $db->prepare('SELECT id FROM flights WHERE id = ? FOR UPDATE')->execute([$flightId]);
        $raceId = $phase === 'review' ? (int) (new Cancellation())->forBooking($race['booking'])[0]['id'] : 0;
        foreach ($phase === 'request' ? ['request','request'] : ['approved','rejected'] as $decision) {
            $pipes = []; $process = proc_open([PHP_BINARY,__FILE__,'--worker',$decision,(string) $race['booking'],(string) $users[$phase === 'request' ? 'customer' : 'admin'],(string) $raceId],[0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],$pipes);
            if (!is_resource($process)) throw new RuntimeException('Race process failed.'); fclose($pipes[0]); $workers[] = ['process' => $process,'stdout' => $pipes[1],'stderr' => $pipes[2]];
            if (trim((string) fgets($pipes[1])) !== 'READY') throw new RuntimeException('Race worker not ready.');
        }
        $db->commit(); $results = [];
        foreach ($workers as $worker) { $results[] = trim(stream_get_contents($worker['stdout'])); $errors = stream_get_contents($worker['stderr']); fclose($worker['stdout']); fclose($worker['stderr']); if (proc_close($worker['process']) !== 0 || $errors !== '') throw new RuntimeException('Race failed: ' . $errors); }
        $workers = []; sort($results); $records = (new Cancellation())->forBooking($race['booking']);
        $assert($results === ['BLOCKED','SAVED'] && count($records) === 1,'concurrent cancellation ' . $phase . ' has one winner');
        if ($phase === 'review') $assert((new Booking())->findForCustomer($race['booking'],$users['customer'])['status'] === ($records[0]['status'] === 'approved' ? 'cancelled' : 'pending'),'concurrent review leaves consistent booking status');
    }
    $assert($request($customer,'GET','/bookings?status=cancellation_requested')['status'] === 200,'cancellation-requested filter works');
    $db->prepare('INSERT INTO cancellations (booking_id, requested_by) VALUES (?, ?)')->execute([$invalid['booking'],$users['customer']]); $legacyId = (int) $db->lastInsertId();
    $migrationPipes = []; $migration = proc_open([PHP_BINARY,BASE_PATH . '/scripts/migrate_module11.php'],[0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],$migrationPipes);
    if (!is_resource($migration)) throw new RuntimeException('Migration preflight process failed.');
    fclose($migrationPipes[0]); $migrationOutput = stream_get_contents($migrationPipes[1]); $migrationError = stream_get_contents($migrationPipes[2]); fclose($migrationPipes[1]); fclose($migrationPipes[2]);
    $assert(proc_close($migration) === 1 && str_contains($migrationError,'previous booking status was not recorded') && $migrationOutput === '', 'migration refuses legacy pending requests with unknown restore state');
    $db->prepare('DELETE FROM cancellations WHERE id = ?')->execute([$legacyId]);
    echo "$checks Module 11 checks passed." . PHP_EOL;
} catch (Throwable $exception) { $failed = true; fwrite(STDERR,'Cancellation test failed: ' . $exception->getMessage() . PHP_EOL); }
finally {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    foreach ($workers as $worker) if (is_resource($worker['process'])) { proc_terminate($worker['process']); proc_close($worker['process']); }
    if ($db instanceof PDO) {
        if ($triggerCreated) $db->exec("DROP TRIGGER $trigger");
        foreach ($bookings as $id) {
            $db->prepare('DELETE FROM tickets WHERE booking_seat_id IN (SELECT id FROM booking_seats WHERE booking_id = ?)')->execute([$id]);
            foreach (['cancellations','payments','booking_seats','passengers'] as $table) $db->prepare("DELETE FROM $table WHERE booking_id = ?")->execute([$id]); $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
        }
        if ($flightId) $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$flightId]);
        if ($aircraftId) { $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$aircraftId]); $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$aircraftId]); }
        foreach ($airports as $id) $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]);
        foreach ($emails as $email) $db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    }
    foreach ($clients as $handle) curl_close($handle); echo 'Unique Module 11 fixtures removed.' . PHP_EOL;
}
exit($failed ? 1 : 0);
