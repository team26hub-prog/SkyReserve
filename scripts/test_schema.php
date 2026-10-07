<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

// Integration checks only: fixtures are rolled back, never committed.
$db = null;
$checks = 0;

try {
    $db = Database::connection();
    $db->beginTransaction();
    $suffix = bin2hex(random_bytes(8));

    $insert = static function (string $sql, array $values) use ($db): int {
        $db->prepare($sql)->execute($values);
        return (int) $db->lastInsertId();
    };

    $reject = static function (string $label, string $sql, array $values) use ($db, &$checks): void {
        try {
            $db->prepare($sql)->execute($values);
        } catch (PDOException $exception) {
            // Duplicate key, foreign key, and CHECK constraint violations only.
            if (!in_array((int) ($exception->errorInfo[1] ?? 0), [1062, 1452, 3819], true)) {
                throw $exception;
            }
            ++$checks;
            echo 'PASS: ' . $label . PHP_EOL;
            return;
        }
        throw new RuntimeException('Constraint did not reject: ' . $label);
    };

    $airport = static function () use ($db, $insert): int {
        do {
            $code = chr(random_int(65, 90)) . chr(random_int(65, 90)) . chr(random_int(65, 90));
            $query = $db->prepare('SELECT id FROM airports WHERE iata_code = ?');
            $query->execute([$code]);
        } while ($query->fetchColumn() !== false);
        return $insert('INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, ?, ?)', [$code, 'Schema test', 'Test city', 'Test country', 'UTC']);
    };

    $user = $insert('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)', ['Schema test', $suffix . '@example.invalid', password_hash($suffix, PASSWORD_DEFAULT)]);
    $origin = $airport();
    $destination = $airport();
    $aircraft = $insert('INSERT INTO aircraft (registration_number, model) VALUES (?, ?)', ['T-' . $suffix, 'Test aircraft']);
    $otherAircraft = $insert('INSERT INTO aircraft (registration_number, model) VALUES (?, ?)', ['U-' . $suffix, 'Test aircraft']);
    $seat = $insert('INSERT INTO seats (aircraft_id, seat_number) VALUES (?, ?)', [$aircraft, '1A']);
    $wrongSeat = $insert('INSERT INTO seats (aircraft_id, seat_number) VALUES (?, ?)', [$otherAircraft, '1A']);

    $flightSql = 'INSERT INTO flights (flight_number, aircraft_id, origin_airport_id, destination_airport_id, departure_at, arrival_at, base_fare) VALUES (?, ?, ?, ?, ?, ?, ?)';
    $flight = $insert($flightSql, ['TEST1', $aircraft, $origin, $destination, '2030-01-01 10:00:00', '2030-01-01 12:00:00', 100]);
    $otherFlight = $insert($flightSql, ['TEST2', $aircraft, $origin, $destination, '2030-01-02 10:00:00', '2030-01-02 12:00:00', 100]);
    $reject('origin must differ from destination', $flightSql, ['TEST3', $aircraft, $origin, $origin, '2030-01-03 10:00:00', '2030-01-03 12:00:00', 100]);
    $reject('arrival must follow departure', $flightSql, ['TEST4', $aircraft, $origin, $destination, '2030-01-04 12:00:00', '2030-01-04 10:00:00', 100]);
    $reject('fare cannot be negative', $flightSql, ['TEST5', $aircraft, $origin, $destination, '2030-01-05 10:00:00', '2030-01-05 12:00:00', -1]);
    $reject('fare must be strictly positive', $flightSql, ['TEST6', $aircraft, $origin, $destination, '2030-01-06 10:00:00', '2030-01-06 12:00:00', 0]);

    $bookingSql = 'INSERT INTO bookings (booking_reference, user_id, flight_id, total_amount) VALUES (?, ?, ?, ?)';
    $booking = $insert($bookingSql, ['A-' . $suffix, $user, $flight, 100]);
    $otherBooking = $insert($bookingSql, ['B-' . $suffix, $user, $flight, 100]);
    $passengerSql = 'INSERT INTO passengers (booking_id, first_name, last_name) VALUES (?, ?, ?)';
    $passenger = $insert($passengerSql, [$booking, 'Test', 'One']);
    $otherPassenger = $insert($passengerSql, [$otherBooking, 'Test', 'Two']);

    $allocationSql = 'INSERT INTO booking_seats (booking_id, passenger_id, flight_id, aircraft_id, seat_id) VALUES (?, ?, ?, ?, ?)';
    $reject('passenger must belong to the booking', $allocationSql, [$booking, $otherPassenger, $flight, $aircraft, $seat]);
    $reject('seat must belong to the flight aircraft', $allocationSql, [$booking, $passenger, $flight, $aircraft, $wrongSeat]);
    $reject('allocation must use the booking flight', $allocationSql, [$booking, $passenger, $otherFlight, $aircraft, $seat]);
    $allocation = $insert($allocationSql, [$booking, $passenger, $flight, $aircraft, $seat]);
    $reject('active flight seat cannot be allocated twice', $allocationSql, [$otherBooking, $otherPassenger, $flight, $aircraft, $seat]);
    $reject('passenger cannot have two allocation rows', $allocationSql, [$booking, $passenger, $flight, $aircraft, $seat]);

    $db->prepare("UPDATE booking_seats SET status = 'released' WHERE id = ?")->execute([$allocation]);
    $insert($allocationSql, [$otherBooking, $otherPassenger, $flight, $aircraft, $seat]);
    ++$checks;
    echo 'PASS: released seat can be reallocated without deleting its previous row' . PHP_EOL;

    $payment = $insert('INSERT INTO payments (booking_id, amount) VALUES (?, ?)', [$booking, 100]);
    $ticketSql = 'INSERT INTO tickets (booking_seat_id, ticket_number) VALUES (?, ?)';
    $insert($ticketSql, [$allocation, 'T-' . $suffix]);
    $reject('one ticket per allocation', $ticketSql, [$allocation, 'U-' . $suffix]);
    $cancellationSql = 'INSERT INTO cancellations (booking_id, requested_by) VALUES (?, ?)';
    $insert($cancellationSql, [$booking, $user]);
    $reject('one cancellation record per booking', $cancellationSql, [$booking, $user]);

    $db->rollBack();
    echo $checks . ' schema checks passed. All fixtures rolled back.' . PHP_EOL;
} catch (Throwable $exception) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Schema test failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
