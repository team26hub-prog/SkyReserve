<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;
use App\Models\AdminReport;
use App\Models\Ticket;

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8000','/'); $url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http' || !in_array($url['host'] ?? '',['localhost','127.0.0.1'],true)
    || isset($url['user']) || isset($url['pass']) || !empty($url['path']) || isset($url['query']) || isset($url['fragment'])) { fwrite(STDERR,'Use a local development server URL.' . PHP_EOL); exit(1); }
if (!extension_loaded('curl')) { fwrite(STDERR,'Tests require curl.' . PHP_EOL); exit(1); }
$db = null; $checks = 0; $failed = false; $users = $emails = $airports = $flights = $bookings = $clients = []; $aircraftId = null;
$suffix = bin2hex(random_bytes(4)); $password = 'Report-' . bin2hex(random_bytes(12));
$assert = static function (bool $condition, string $message) use (&$checks): void { if (!$condition) throw new RuntimeException($message); ++$checks; echo 'PASS: ' . $message . PHP_EOL; };
$client = static function () use (&$clients): CurlHandle { $handle = curl_init(); curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER => true,CURLOPT_COOKIEFILE => '',CURLOPT_FOLLOWLOCATION => false,CURLOPT_TIMEOUT => 15]); $clients[] = $handle; return $handle; };
$request = static function (CurlHandle $handle, string $method, string $path, array $fields = []) use ($base): array {
    curl_setopt($handle,CURLOPT_HTTPGET,true);
    if ($method === 'POST') curl_setopt($handle,CURLOPT_POSTFIELDS,http_build_query($fields));
    curl_setopt_array($handle,[CURLOPT_URL => $base . $path,CURLOPT_CUSTOMREQUEST => $method,CURLOPT_NOBODY => $method === 'HEAD']);
    $body = curl_exec($handle); if ($body === false) throw new RuntimeException(curl_error($handle));
    return ['status' => curl_getinfo($handle,CURLINFO_RESPONSE_CODE),'body' => $body];
};
$token = static function (array $response): string { if (!preg_match('/name="_token" value="([a-f0-9]{64})"/',$response['body'],$match)) throw new RuntimeException('CSRF token missing.'); return $match[1]; };
try {
    $db = Database::connection(); $model = new AdminReport(); $before = $model->dashboard();
    $analyticsDate = new DateTimeImmutable('2025-02-04', new DateTimeZone('UTC'));
    $analyticsBefore = $model->analytics(['period' => '7'], $analyticsDate);
    foreach (['customer','other','admin'] as $role) { $emails[$role] = "report-$role-$suffix@example.invalid"; $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['<b>Report ' . $role . '</b>',$emails[$role],password_hash($password,PASSWORD_DEFAULT),$role === 'admin' ? 'admin' : 'customer']); $users[$role] = (int) $db->lastInsertId(); }
    $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$users['other']]);
    foreach (['Origin','Destination'] as $name) {
        do { $code = chr(random_int(65,90)) . chr(random_int(65,90)) . chr(random_int(65,90)); $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $query->execute([$code]); } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code,'Report ' . $name,$name]); $airports[] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 4)')->execute(['R12-' . $suffix,'<b>Report aircraft</b>']); $aircraftId = (int) $db->lastInsertId(); $seats = [];
    foreach (['1A','2A','3A','4A'] as $number) { $db->prepare('INSERT INTO seats (aircraft_id, seat_number, cabin_class) VALUES (?, ?, ?)')->execute([$aircraftId,$number,$number === '1A' ? 'business' : 'economy']); $seats[] = (int) $db->lastInsertId(); }
    $day = gmdate('Y-m-d',time()+172800);
    foreach (['scheduled','delayed','cancelled','completed','past'] as $index => $status) {
        $departure = $status === 'past' ? '2000-01-01 10:00:00' : $day . ' 10:00:00'; $arrival = $status === 'past' ? '2000-01-01 12:00:00' : $day . ' 12:00:00';
        $db->prepare('INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare, status) VALUES (?, ?, ?, ?, ?, ?, 100.25, ?)')->execute(['R' . $suffix . $index,$aircraftId,$airports[0],$airports[1],$departure,$arrival,$status === 'past' ? 'scheduled' : $status]); $flights[] = (int) $db->lastInsertId();
    }
    $make = static function (string $tag, int $flightId, string $status, string $amount, string $currency = 'PKR', string $created = '2025-02-01 00:00:00') use ($db,$suffix,$users,&$bookings): int {
        $db->prepare('INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount, currency, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute(['M12-' . $tag . '-' . $suffix,$users['customer'],$flightId,$amount,$currency,$status,$created]); $id = (int) $db->lastInsertId(); $bookings[] = $id; return $id;
    };
    $confirmed = $make('OK',$flights[0],'confirmed','100.25'); $submitted = $make('PAY',$flights[0],'payment_submitted','200.20','PKR','2025-02-02 23:59:59');
    $requested = $make('REQ',$flights[0],'cancellation_requested','200.10','USD','2025-02-03 00:00:00'); $cancelled = $make('CAN',$flights[0],'cancelled','300.40'); $unpaid = $make('UNPAID',$flights[0],'pending','1.00');
    for ($i=0; $i<52; ++$i) $make('PAGE' . $i,$flights[1],'pending','1.01','PKR','2025-02-04 12:00:00');
    $passengers = $allocations = [];
    foreach ([[$confirmed,$seats[0]],[$confirmed,$seats[1]],[$cancelled,$seats[2]],[$submitted,null]] as $index => [$bookingId,$seatId]) {
        $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name, full_name, document_number, phone) VALUES (?, 'Report', 'Passenger', ?, ?, '+923001234567')")->execute([$bookingId,'<i>Report Passenger ' . $index . '</i>','AB12345' . $index]); $passengers[] = $passengerId = (int) $db->lastInsertId();
        if ($seatId) { $db->prepare('INSERT INTO booking_seats (booking_id, passenger_id, flight_id, aircraft_id, seat_id, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$bookingId,$passengerId,$flights[0],$aircraftId,$seatId,$bookingId === $cancelled ? 'released' : 'reserved']); $allocations[] = (int) $db->lastInsertId(); }
    }
    $paymentIds = [];
    foreach ([[$confirmed,'rejected','35.75','PKR','2025-02-01 00:00:00'],[$confirmed,'verified','100.25','PKR','2025-02-02 23:59:59'],[$submitted,'pending','210.20','PKR','2025-02-03 00:00:00'],[$requested,'verified','100.10','USD','2025-02-01 00:00:00'],[$cancelled,'verified','300.40','PKR','2025-02-01 00:00:00'],[$unpaid,'refunded','10.00','PKR','2025-02-01 00:00:00']] as [$bookingId,$status,$amount,$currency,$created]) {
        $db->prepare('INSERT INTO payments (booking_id, amount, currency, method, transaction_reference, status, payment_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$bookingId,$amount,$currency,'cash','REF-' . $suffix,$status,'2025-02-01',$created]); $paymentIds[] = (int) $db->lastInsertId();
    }
    $ticketIds = (new Ticket())->generate($confirmed,['id' => $users['admin']]);
    $db->prepare("INSERT INTO tickets (booking_seat_id, ticket_number, status) VALUES (?, ?, 'void')")->execute([$allocations[2],'M12-VOID-' . $suffix]);
    $cancellationIds = [];
    foreach ([[$requested,'pending','2025-02-01 00:00:00'],[$cancelled,'rejected','2025-02-02 23:59:59'],[$cancelled,'approved','2025-02-03 00:00:00']] as [$bookingId,$status,$created]) {
        $db->prepare('INSERT INTO cancellations (booking_id, requested_by, previous_booking_status, reason, status, reviewed_by, reviewed_at, review_notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$bookingId,$users['customer'],'confirmed','<script>Report reason</script>',$status,$status === 'pending' ? null : $users['admin'],$status === 'pending' ? null : '2025-02-05 12:00:00','<b>Report note</b>',$created]); $cancellationIds[] = (int) $db->lastInsertId();
    }
    $metrics = $model->dashboard();
    foreach (['customers' => 2,'flights' => 5,'upcoming_flights' => 2,'bookings' => 57,'confirmed_bookings' => 1,'pending_payments' => 1,'pending_cancellations' => 1] as $key => $delta) $assert((int) $metrics[$key] === (int) $before[$key]+$delta,'dashboard metric delta: ' . $key);
    $expectedRevenue = $db->query("SELECT currency,SUM(amount) AS amount FROM payments WHERE status = 'verified' GROUP BY currency ORDER BY currency")->fetchAll();
    $assert($metrics['revenue'] === $expectedRevenue,'dashboard verified revenue uses exact decimal sums per currency including cancelled verified payments');
    $analytics = $model->analytics(['period' => '7'], $analyticsDate);
    $statusCounts = array_column($analytics['statuses'], 'count', 'status');
    $beforeCounts = array_column($analyticsBefore['statuses'], 'count', 'status');
    foreach (['pending' => 53, 'confirmed' => 1, 'payment_submitted' => 1, 'cancellation_requested' => 1, 'cancelled' => 1, 'expired' => 0] as $status => $delta) {
        $assert($statusCounts[$status] === $beforeCounts[$status] + $delta, 'analytics counts existing booking status accurately: ' . $status);
    }
    $assert(count($analytics['statuses']) === count(App\Models\Booking::STATUSES), 'analytics includes every booking status, including zero counts');
    $beforeBuckets = array_column($analyticsBefore['bookings'], 'count', 'bucket');
    $actualBuckets = array_column($analytics['bookings'], 'count', 'bucket');
    foreach (['2025-02-01' => 3, '2025-02-02' => 1, '2025-02-03' => 1, '2025-02-04' => 52, '2025-01-29' => 0] as $bucket => $delta) {
        $assert($actualBuckets[$bucket] === $beforeBuckets[$bucket] + $delta, 'daily booking trend counts and zero fill: ' . $bucket);
    }
    $assert(count($analytics['bookings']) === 7 && $analytics['start'] === '2025-01-29' && $analytics['end'] === '2025-02-04', '7-day window includes today and UTC midnight boundaries');
    $cents = static fn (string $amount): int => (int) str_replace('.', '', $amount);
    $revenueMap = static function (array $result) use ($cents): array {
        $map = []; foreach ($result['revenue'] as $series) foreach ($series['points'] as $point) $map[$series['currency']][$point['bucket']] = $cents($point['amount']);
        return $map;
    };
    $oldRevenue = $revenueMap($analyticsBefore); $newRevenue = $revenueMap($analytics);
    foreach ([['PKR', '2025-02-01', 30040], ['PKR', '2025-02-02', 10025], ['USD', '2025-02-01', 10010], ['PKR', '2025-02-03', 0]] as [$currency, $bucket, $delta]) {
        $assert($newRevenue[$currency][$bucket] === ($oldRevenue[$currency][$bucket] ?? 0) + $delta, 'revenue is verified only and currencies remain separated: ' . $currency . '/' . $bucket);
    }
    $assert(count($analytics['revenue'][0]['points']) === 7, 'revenue series zero-fill each date independently');
    $monthly = $model->analytics(['period' => '12m'], $analyticsDate);
    $assert(count($monthly['bookings']) === 12 && $monthly['monthly'] && $monthly['start'] === '2024-03-01' && $monthly['bookings'][11]['bucket'] === '2025-02', '12-month view handles year transitions and current month');
    $query = $db->prepare('SELECT COUNT(*) FROM bookings WHERE created_at >= ? AND created_at < ?'); $query->execute(['2025-02-01', '2025-02-05']);
    $assert($monthly['bookings'][11]['count'] === (int) $query->fetchColumn(), 'monthly booking count includes today and excludes future timestamps');
    $query = $db->prepare("SELECT currency,SUM(amount) AS amount FROM payments WHERE status = 'verified' AND created_at >= ? AND created_at < ? GROUP BY currency"); $query->execute(['2025-02-01', '2025-02-05']);
    $monthlyRevenue = array_column($monthly['revenue'], 'points', 'currency');
    foreach ($query->fetchAll() as $row) $assert($monthlyRevenue[$row['currency']][11]['amount'] === $row['amount'], 'monthly verified revenue uses exact decimal aggregation: ' . $row['currency']);
    $defaultAnalytics = $model->analytics([], $analyticsDate);
    $assert($defaultAnalytics['period'] === '30' && count($defaultAnalytics['bookings']) === 30, 'analytics defaults to last 30 days');
    $renderAnalytics = static function (array $chartData): string {
        $data = ['analytics' => $chartData, 'analyticsError' => null];
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start(); require BASE_PATH . '/app/Views/admin/reports/analytics.php'; return ob_get_clean();
    };
    $unsafe = $analytics; $unsafe['revenue'][0]['currency'] = 'USD</script><script>alert(1)</script>';
    $unsafe['routes'][0]['origin'] = '<img onerror="alert(1)">';
    $escapedCharts = $renderAnalytics($unsafe);
    $assert(!str_contains($escapedCharts, '<script>alert(1)') && !str_contains($escapedCharts, '<img onerror=') && str_contains($escapedCharts, 'USD&lt;/script&gt;') && str_contains($escapedCharts, '\\u003C'), 'chart headings, tables and embedded JSON escape hostile stored values');
    $emptyCharts = $analytics; $emptyCharts['revenue'] = $emptyCharts['routes'] = [];
    foreach (['statuses', 'bookings'] as $key) foreach ($emptyCharts[$key] as &$row) $row['count'] = 0;
    unset($row);
    $assert(substr_count($renderAnalytics($emptyCharts), 'class="chart-empty"') === 4, 'every chart renders a professional empty state without data');
    $expectedRoutes = $db->query('SELECT o.iata_code AS origin,d.iata_code AS destination,COUNT(*) AS count FROM bookings b JOIN flights f ON f.id=b.flight_id JOIN airports o ON o.id=f.origin_airport_id JOIN airports d ON d.id=f.destination_airport_id GROUP BY o.id,d.id,o.iata_code,d.iata_code ORDER BY count DESC,o.iata_code,d.iata_code LIMIT 5')->fetchAll();
    $assert($analytics['routes'] === $expectedRoutes && count($analytics['routes']) <= 5, 'top routes aggregate bookings without passenger/payment fanout and use deterministic top-five ordering');
    foreach ([['period' => 'bad'], ['period' => ['7']], ['period' => '7 OR 1=1'], ['unexpected' => '1'], ['period' => '']] as $invalid) {
        try { $model->analytics($invalid); throw new RuntimeException('Invalid analytics period accepted.'); }
        catch (DomainException) { $assert(true, 'analytics rejects malformed filters'); }
    }
    $guest = $client(); $customer = $client(); $admin = $client();
    foreach ([[$customer,'customer','/login'],[$admin,'admin','/login']] as [$handle,$role,$login]) { $form = $request($handle,'GET',$login); $assert($request($handle,'POST',$login,['_token' => $token($form),'email' => $emails[$role],'password' => $password])['status'] === 303,'fixture ' . $role . ' login'); }
    $dashboard = $request($admin,'GET','/admin');
    foreach (['customers','flights','upcoming_flights','bookings','confirmed_bookings','pending_payments','pending_cancellations'] as $key) $assert(str_contains($dashboard['body'],'data-metric="' . $key . '">' . $metrics[$key] . '</strong>'),'dashboard renders accurate ' . $key);
    foreach ($expectedRevenue as $revenue) $assert(str_contains($dashboard['body'],$revenue['currency'] . ' ' . $revenue['amount']),'dashboard renders currency revenue: ' . $revenue['currency']);
    $snapshot = static function () use ($db,$bookings,$flights,$paymentIds,$cancellationIds): array {
        $sets = [];
        foreach (['bookings' => $bookings,'flights' => $flights,'payments' => $paymentIds,'cancellations' => $cancellationIds] as $table => $ids) { $query = $db->prepare("SELECT * FROM $table WHERE id IN (" . implode(',',array_fill(0,count($ids),'?')) . ') ORDER BY id'); $query->execute($ids); $sets[$table] = $query->fetchAll(); }
        foreach (['passengers','booking_seats'] as $table) { $query = $db->prepare("SELECT * FROM $table WHERE booking_id IN (" . implode(',',array_fill(0,count($bookings),'?')) . ') ORDER BY id'); $query->execute($bookings); $sets[$table] = $query->fetchAll(); }
        $query = $db->prepare('SELECT t.* FROM tickets t JOIN booking_seats bs ON bs.id = t.booking_seat_id WHERE bs.booking_id IN (' . implode(',',array_fill(0,count($bookings),'?')) . ') ORDER BY t.id'); $query->execute($bookings); $sets['tickets'] = $query->fetchAll();
        return $sets;
    };
    $storedBefore = $snapshot();
    foreach (['7', '30', '12m'] as $period) {
        $response = $request($admin, 'GET', '/admin/reports?period=' . $period);
        $assert($response['status'] === 200 && str_contains($response['body'], 'id="analytics-data"'), 'admin analytics period renders: ' . $period);
        foreach (AdminReport::REPORTS as $type => $report) $assert(str_contains($response['body'], 'href="/admin/reports/' . $type . '"'), 'existing report card preserved with analytics: ' . $type);
    }
    foreach (['period=invalid', 'period[]=7', 'period=7%20OR%201%3D1', 'unknown=1', 'period='] as $invalid) {
        $response = $request($admin, 'GET', '/admin/reports?' . $invalid);
        $assert($response['status'] === 422 && !str_contains($response['body'], 'id="analytics-data"'), 'invalid analytics filters expose no chart data: ' . $invalid);
    }
    foreach (['',...array_map(static fn (string $type): string => '/' . $type,array_keys(AdminReport::REPORTS))] as $suffixPath) {
        $path = '/admin/reports' . $suffixPath;
        $assert($request($guest,'GET',$path)['status'] === 303,'guest report blocked: ' . $path);
        $assert($request($customer,'GET',$path)['status'] === 403,'customer report blocked: ' . $path);
        $assert($request($customer,'HEAD',$path)['status'] === 403,'HEAD role protection: ' . $path);
        $assert($request($admin,'GET',$path)['status'] === 200,'admin report accessible: ' . $path);
        $head = $request($admin,'HEAD',$path); $assert($head['status'] === 200 && $head['body'] === '','admin HEAD sends no body: ' . $path);
        $assert($request($admin,'POST',$path,['_token' => $token($dashboard)])['status'] === 405,'reports are GET-only: ' . $path);
    }
    $selected = ['flight_id' => (string) $flights[0]];
    $book = $model->report('bookings',$selected);
    $assert($book['total'] === 5 && count($book['rows']) === 5,'bookings report has one row per booking without passenger/payment/cancellation fanout');
    $confirmedRow = array_values(array_filter($book['rows'],static fn (array $row): bool => (int) $row['id'] === $confirmed))[0];
    $assert((int) $confirmedRow['passengers'] === 2 && (int) $confirmedRow['seats'] === 2 && (int) $confirmedRow['tickets'] === 2 && $confirmedRow['payment_status'] === 'verified','bookings counts and latest payment are accurate');
    $assert($book['totals'] === [['currency' => 'PKR','amount' => '601.85'],['currency' => 'USD','amount' => '200.10']],'booked value totals exact per currency without multiplying joined records');
    $bookingPage = $request($admin,'GET','/admin/reports/bookings?' . http_build_query($selected));
    $assert(str_contains($bookingPage['body'],'&lt;b&gt;Report customer&lt;/b&gt;') && !str_contains($bookingPage['body'],'<b>Report customer</b>'),'booking report escapes customer data');
    $assert($model->report('bookings',$selected+['booking_status' => 'confirmed'])['total'] === 1,'booking status filter');
    $assert($model->report('bookings',$selected+['payment_status' => 'verified'])['total'] === 3,'latest verified payment filter');
    $assert($model->report('bookings',$selected+['payment_status' => 'not_submitted'])['total'] === 0,'not-submitted filter excludes historical refunded payments');
    $assert($model->report('bookings',['flight_id' => (string) $flights[1],'payment_status' => 'not_submitted'])['total'] === 52,'not-submitted filter includes bookings with no payment rows');
    $assert($model->report('bookings',$selected+['start_date' => '2025-02-01','end_date' => '2025-02-02'])['total'] === 4,'booking UTC date boundaries include midnight/end-of-day and exclude following day');
    $assert($model->report('bookings',$selected+['start_date' => '2025-02-03'])['total'] === 1,'open-ended start date filter');
    $assert($model->report('bookings',$selected+['end_date' => '2025-02-01'])['total'] === 3,'open-ended end date filter');
    $paged = $model->report('bookings',['flight_id' => (string) $flights[1]]);
    $page2 = $model->report('bookings',['flight_id' => (string) $flights[1],'page' => '2']);
    $assert($paged['total'] === 52 && count($paged['rows']) === 50 && count($page2['rows']) === 2 && $paged['pages'] === 2,'reports paginate at 50 rows');
    $assert(!array_intersect(array_column($paged['rows'],'id'),array_column($page2['rows'],'id')) && $page2['totals'][0]['amount'] === '52.52','pagination has no repeated rows and totals cover every matching record');
    $assert($model->report('bookings',['flight_id' => (string) $flights[1],'page' => '999999'])['page'] === 2,'out-of-range positive page clamps without huge offset');
    $paginationHtml = $request($admin,'GET','/admin/reports/bookings?flight_id=' . $flights[1]);
    $assert(str_contains($paginationHtml['body'],'Page 1 of 2') && str_contains($paginationHtml['body'],'flight_id=' . $flights[1] . '&amp;page=2'),'pagination preserves filters in next link');
    $flightReport = $model->report('flights',$selected); $flightRow = $flightReport['rows'][0];
    $assert($flightReport['total'] === 1 && (int) $flightRow['bookings'] === 5 && (int) $flightRow['configured'] === 4 && (int) $flightRow['occupied'] === 2 && (int) $flightRow['unassigned'] === 2,'flight counts exclude released seats and avoid booking fanout');
    $assert($model->report('flights',$selected+['start_date' => $day,'end_date' => $day,'flight_status' => 'scheduled'])['total'] === 1,'flight departure date and status filters');
    $assert($model->report('flights',$selected+['end_date' => '2025-02-02'])['total'] === 0,'flight dates use departure rather than record creation');
    $flightHtml = $request($admin,'GET','/admin/reports/flights?' . http_build_query($selected));
    $assert(str_contains($flightHtml['body'],'&lt;b&gt;Report aircraft&lt;/b&gt;'),'flight report escapes aircraft model');
    $payment = $model->report('payments',$selected);
    $revenue = $model->report('revenue', $selected);
    $assert($revenue['total'] === 3 && count($revenue['rows']) === 3 && array_unique(array_column($revenue['rows'], 'payment_status')) === ['verified'], 'revenue returns only verified payment records without join fanout');
    $assert($revenue['totals'] === [['currency' => 'PKR', 'amount' => '400.65'], ['currency' => 'USD', 'amount' => '100.10']], 'revenue sums exact decimal amounts separately per currency, including cancelled bookings');
    $assert($model->report('revenue', $selected + ['start_date' => '2025-02-02', 'end_date' => '2025-02-02'])['totals'] === [['currency' => 'PKR', 'amount' => '100.25']], 'revenue submission-date range includes end-of-day boundary');
    $assert($model->report('revenue', $selected + ['start_date' => '2025-02-03'])['total'] === 0, 'revenue excludes pending payments in a matching date range');
    $assert($model->report('revenue', ['flight_id' => (string) $flights[1]])['rows'] === [], 'revenue flight filter produces an accurate empty state');
    foreach (['pending', 'verified', 'rejected', 'refunded'] as $status) {
        try { $model->report('revenue', $selected + ['payment_status' => $status]); throw new RuntimeException('Revenue accepted a status override.'); }
        catch (DomainException) { $assert(true, 'revenue rejects payment-status overrides: ' . $status); }
    }
    $revenueHtml = $request($admin, 'GET', '/admin/reports/revenue?' . http_build_query($selected));
    $assert(str_contains($revenueHtml['body'], 'Verified revenue') && str_contains($revenueHtml['body'], 'PKR 400.65') && str_contains($revenueHtml['body'], 'USD 100.10') && str_contains($revenueHtml['body'], '&lt;b&gt;Report customer&lt;/b&gt;'), 'revenue page renders separate currency totals and escapes stored data');
    $assert(str_contains($revenueHtml['body'], '/admin/payments/show?id=' . $paymentIds[1]), 'revenue records link to existing protected payment details');
    $assert(str_contains($request($admin, 'GET', '/admin/reports/revenue?flight_id=' . $flights[1])['body'], 'No records match these filters.'), 'empty revenue page shows the shared professional empty state');
    $db->beginTransaction();
    try {
        $insert = $db->prepare("INSERT INTO payments (booking_id, amount, currency, method, transaction_reference, status, payment_date, created_at) VALUES (?, '1.01', 'PKR', 'cash', ?, 'verified', '2025-02-01', '2025-02-01 12:00:00')");
        foreach (array_slice($bookings, -52) as $i => $bookingId) $insert->execute([$bookingId, 'REVENUE-PAGE-' . $suffix . '-' . $i]);
        $revenueFlight = ['flight_id' => (string) $flights[1]];
        $revenueFirst = $model->report('revenue', $revenueFlight);
        $revenueSecond = $model->report('revenue', $revenueFlight + ['page' => '2']);
        $assert($revenueFirst['total'] === 52 && count($revenueFirst['rows']) === 50 && count($revenueSecond['rows']) === 2 && !array_intersect(array_column($revenueFirst['rows'], 'id'), array_column($revenueSecond['rows'], 'id')), 'revenue paginates verified records without repetition');
        $assert($revenueFirst['totals'] === $revenueSecond['totals'] && $revenueSecond['totals'] === [['currency' => 'PKR', 'amount' => '52.52']], 'revenue currency totals cover all matching pages rather than just visible rows');
    } finally { $db->rollBack(); }
    $assert($payment['total'] === 6 && $payment['totals'] === [['currency' => 'PKR','amount' => '656.60','verified_amount' => '400.65'],['currency' => 'USD','amount' => '100.10','verified_amount' => '100.10']],'payments report includes history and exact submitted/verified totals per currency');
    foreach (['pending' => 1,'verified' => 3,'rejected' => 1,'refunded' => 1] as $status => $count) $assert($model->report('payments',$selected+['payment_status' => $status])['total'] === $count,'payment status filter: ' . $status);
    $assert($model->report('payments',$selected+['booking_status' => 'cancelled'])['total'] === 1,'payment booking status filter');
    $assert($model->report('payments',$selected+['start_date' => '2025-02-02','end_date' => '2025-02-02'])['total'] === 1,'payment dates use submission timestamp and inclusive range');
    $cancel = $model->report('cancellations',$selected);
    $assert($cancel['total'] === 3,'cancellations report preserves rejected and approved request history');
    foreach (['pending','approved','rejected'] as $status) $assert($model->report('cancellations',$selected+['cancellation_status' => $status])['total'] === 1,'cancellation status filter: ' . $status);
    $assert($model->report('cancellations',$selected+['booking_status' => 'cancelled'])['total'] === 2,'cancellation booking status filter');
    $assert($model->report('cancellations',$selected+['start_date' => '2025-02-02','end_date' => '2025-02-02'])['total'] === 1,'cancellation request date filter');
    $cancelHtml = $request($admin,'GET','/admin/reports/cancellations?' . http_build_query($selected));
    $assert(str_contains($cancelHtml['body'],'&lt;script&gt;Report reason&lt;/script&gt;') && str_contains($cancelHtml['body'],'&lt;b&gt;Report note&lt;/b&gt;'),'cancellation report escapes reasons and admin notes');
    $passengerReport = $model->report('passengers',$selected);
    $assert($passengerReport['total'] === 4 && count($passengerReport['rows']) === 4,'passenger-by-flight report returns each passenger once');
    $passengerRows = array_column($passengerReport['rows'],null,'id');
    $assert($passengerRows[$passengers[0]]['seat_number'] === '1A' && $passengerRows[$passengers[0]]['cabin_class'] === 'business' && $passengerRows[$passengers[0]]['ticket_status'] === 'valid','passenger report joins assigned seat and ticket');
    $assert($passengerRows[$passengers[2]]['seat_status'] === 'released' && $passengerRows[$passengers[2]]['ticket_status'] === 'void' && $passengerRows[$passengers[3]]['seat_number'] === null,'passenger report retains historical released/void and unassigned cases');
    $assert($model->report('passengers',$selected+['booking_status' => 'confirmed'])['total'] === 2,'passenger booking status filter');
    $assert($model->report('passengers',$selected+['start_date' => $day,'end_date' => $day])['total'] === 4,'passenger dates use flight departure');
    $assert($model->report('passengers',[])['total'] === 0,'passenger report requires flight selection before showing data');
    $passengerHtml = $request($admin,'GET','/admin/reports/passengers?' . http_build_query($selected));
    $assert(str_contains($passengerHtml['body'],'&lt;i&gt;Report Passenger 0&lt;/i&gt;') && str_contains($passengerHtml['body'],'AB123450') && str_contains($passengerHtml['body'],'/admin/tickets/show?id=' . $ticketIds[0]),'passenger page contains escaped identity and protected ticket link');
    foreach (array_keys(AdminReport::REPORTS) as $type) {
        $empty = $request($admin,'GET','/admin/reports/' . $type . '?flight_id=' . $flights[2] . '&start_date=1900-01-01&end_date=1900-01-01');
        $assert($empty['status'] === 200 && str_contains($empty['body'],'No records match these filters') && str_contains($empty['body'],'data-report-total="0"'),'empty state: ' . $type);
        foreach (['start_date=2025-02-30','start_date=2025-01-02&end_date=2025-01-01','end_date=0000-01-01','start_date[]=2025-01-01','flight_id=2147483647','flight_id=-1','flight_id[]=1','page=0','page=1.5','page[]=1','page=1000001','unexpected=1'] as $invalid) {
            $response = $request($admin,'GET','/admin/reports/' . $type . '?' . $invalid);
            $assert($response['status'] === 422 && !str_contains($response['body'],'data-report-row='),'invalid filters show no data: ' . $type . '/' . $invalid);
        }
        foreach (AdminReport::REPORTS[$type]['statuses'] as $field) $assert($request($admin,'GET','/admin/reports/' . $type . '?' . $field . '=invalid')['status'] === 422,'invalid status filter: ' . $type . '/' . $field);
    }
    $injected = $request($admin,'GET','/admin/reports/bookings?start_date=' . rawurlencode('" autofocus onfocus="alert(1)'));
    $assert($injected['status'] === 422 && !str_contains($injected['body'],'value="" autofocus') && !str_contains($injected['body'],BASE_PATH),'invalid filter values cannot inject markup or expose paths');
    $assert($snapshot() === $storedBefore,'dashboard and report browsing leave bookings/flights/payments/passengers/seats/tickets/cancellations unchanged');
    echo "$checks Module 12 checks passed." . PHP_EOL;
} catch (Throwable $exception) { $failed = true; fwrite(STDERR,'Report test failed: ' . $exception->getMessage() . PHP_EOL); }
finally {
    if ($db instanceof PDO) {
        foreach ($bookings as $id) {
            $db->prepare('DELETE FROM tickets WHERE booking_seat_id IN (SELECT id FROM booking_seats WHERE booking_id = ?)')->execute([$id]);
            foreach (['cancellations','payments','booking_seats','passengers'] as $table) $db->prepare("DELETE FROM $table WHERE booking_id = ?")->execute([$id]); $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
        }
        foreach ($flights as $id) $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$id]);
        if ($aircraftId) { $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$aircraftId]); $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$aircraftId]); }
        foreach ($airports as $id) $db->prepare('DELETE FROM airports WHERE id = ?')->execute([$id]); foreach ($emails as $email) $db->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
    }
    foreach ($clients as $handle) curl_close($handle); echo 'Unique Module 12 fixtures removed.' . PHP_EOL;
}
exit($failed ? 1 : 0);
