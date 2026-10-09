<?php
declare(strict_types=1);

// CLI-only, disposable data for the browser presentation checks.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\Database;

if (($argv[1] ?? '') === '--analytics-empty') {
    $analytics = ['period' => '30', 'monthly' => false, 'start' => gmdate('Y-m-d', time() - 29 * 86400), 'end' => gmdate('Y-m-d'), 'statuses' => [], 'bookings' => [], 'revenue' => [], 'routes' => []];
    foreach (App\Models\Booking::STATUSES as $status => $label) $analytics['statuses'][] = ['status' => $status, 'label' => $label, 'count' => 0];
    $data = ['title' => 'Reports', 'user' => ['role' => 'admin', 'name' => 'Preview Admin'], 'flash' => null, 'wide' => true, 'analytics' => $analytics, 'analyticsError' => null];
    $view = 'admin/reports/index'; $viewFile = BASE_PATH . '/app/Views/admin/reports/index.php';
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $csrf = str_repeat('0', 64);
    require BASE_PATH . '/app/Views/layouts/base.php';
    exit;
}

if (in_array($argv[1] ?? '', ['--search-empty', '--search-empty-customer'], true)) {
    $data = ['title' => 'Search flights', 'user' => ($argv[1] === '--search-empty-customer') ? ['role' => 'customer', 'name' => 'Preview Customer'] : null, 'flash' => null, 'upcomingFlights' => [], 'airports' => [], 'values' => ['from_airport_id' => '', 'to_airport_id' => '', 'travel_date' => ''], 'errors' => [], 'submitted' => false, 'today' => gmdate('Y-m-d')];
    $view = 'customer/flights/search'; $viewFile = BASE_PATH . '/app/Views/customer/flights/search.php';
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $csrf = str_repeat('0', 64);
    require BASE_PATH . '/app/Views/layouts/base.php';
    exit;
}

$db = Database::connection();
if (($argv[1] ?? '') === '--cleanup') {
    $fixture = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM tickets WHERE booking_seat_id IN (SELECT id FROM booking_seats WHERE booking_id = ?)')->execute([$fixture['bookingId']]);
        foreach (['cancellations', 'payments', 'booking_seats', 'passengers'] as $table) {
            $db->prepare("DELETE FROM $table WHERE booking_id = ?")->execute([$fixture['bookingId']]);
        }
        $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$fixture['bookingId']]);
        $db->prepare('DELETE FROM flights WHERE id = ?')->execute([$fixture['flightId']]);
        $db->prepare('DELETE FROM seats WHERE aircraft_id = ?')->execute([$fixture['aircraftId']]);
        $db->prepare('DELETE FROM aircraft WHERE id = ?')->execute([$fixture['aircraftId']]);
        $db->prepare('DELETE FROM airports WHERE id IN (?, ?)')->execute([$fixture['from'], $fixture['to']]);
        $db->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([$fixture['customerEmail'], $fixture['adminEmail']]);
        if (isset($fixture['registeredEmail'])) $db->prepare('DELETE FROM users WHERE email = ?')->execute([$fixture['registeredEmail']]);
        $db->commit();
    } catch (Throwable $exception) { $db->rollBack(); throw $exception; }
    exit;
}
if (($argv[1] ?? '') !== '--create') { fwrite(STDERR, 'Use --create or --cleanup.' . PHP_EOL); exit(1); }
$suffix = bin2hex(random_bytes(5));
$fixture = ['customerEmail' => "ui-customer-$suffix@example.invalid", 'adminEmail' => "ui-admin-$suffix@example.invalid", 'password' => 'Ui-' . bin2hex(random_bytes(12)), 'date' => gmdate('Y-m-d', time() + 172800)];
$db->beginTransaction();
try {
    foreach (['customer', 'admin'] as $role) {
        $db->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')->execute(['UI Preview ' . ucfirst($role), $fixture[$role . 'Email'], password_hash($fixture['password'], PASSWORD_DEFAULT), $role]);
        $fixture[$role . 'Id'] = (int) $db->lastInsertId();
    }
    foreach (['from' => 'Preview Origin', 'to' => 'Preview Destination'] as $key => $name) {
        do {
            $code = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
            $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?'); $query->execute([$code]);
        } while ($query->fetchColumn() !== false);
        $db->prepare("INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, 'Test', 'UTC')")->execute([$code, $name, $name]);
        $fixture[$key] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, 12)')->execute(['UI-' . $suffix, 'Airbus A320 Preview']);
    $fixture['aircraftId'] = (int) $db->lastInsertId();
    foreach (['1A', '1B', '1C', '2A', '2B', '2C', '3A', '3B', '3C', '4A', '4B', '4C'] as $number) {
        $db->prepare('INSERT INTO seats (aircraft_id, seat_number, cabin_class) VALUES (?, ?, ?)')->execute([$fixture['aircraftId'], $number, str_starts_with($number, '1') ? 'business' : 'economy']);
        if ($number === '2A') $fixture['seatId'] = (int) $db->lastInsertId();
    }
    $db->prepare('INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare) VALUES (?, ?, ?, ?, ?, ?, 24500)')->execute(['UI' . strtoupper($suffix), $fixture['aircraftId'], $fixture['from'], $fixture['to'], $fixture['date'] . ' 10:00:00', $fixture['date'] . ' 12:00:00']);
    $fixture['flightId'] = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount) VALUES (?, ?, ?, 24500)')->execute(['UI-' . strtoupper($suffix), $fixture['customerId'], $fixture['flightId']]);
    $fixture['bookingId'] = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO passengers (booking_id, first_name, last_name, full_name, document_number, date_of_birth, gender, phone) VALUES (?, 'Preview', 'Passenger', 'Preview Passenger', 'AB123456', '1990-01-01', 'female', '+923001234567')")->execute([$fixture['bookingId']]);
    $db->commit();
    echo json_encode($fixture, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) { $db->rollBack(); throw $exception; }
