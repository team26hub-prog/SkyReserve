<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use App\Core\Database;

$db = null;
$locked = false;
try {
    require dirname(__DIR__) . '/bootstrap.php';
    $db = Database::connection();
    // Serialize seed runs; aircraft row locks also coordinate with admin seat edits.
    $lockName = 'skyreserve_inventory_' . substr(hash('sha256', (string) $db->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    $lock = $db->prepare('SELECT GET_LOCK(?, 15)');
    $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Another inventory seed is running. Try again shortly.');
    $locked = true;
    $db->beginTransaction();
    $airports = [
        ['LHE', 'Allama Iqbal International Airport', 'Lahore', 'Pakistan', 'Asia/Karachi'],
        ['KHI', 'Jinnah International Airport', 'Karachi', 'Pakistan', 'Asia/Karachi'],
        ['ISB', 'Islamabad International Airport', 'Islamabad', 'Pakistan', 'Asia/Karachi'],
        ['DXB', 'Dubai International Airport', 'Dubai', 'United Arab Emirates', 'Asia/Dubai'],
        ['DOH', 'Hamad International Airport', 'Doha', 'Qatar', 'Asia/Qatar'],
        ['JED', 'King Abdulaziz International Airport', 'Jeddah', 'Saudi Arabia', 'Asia/Riyadh'],
    ];
    $airportCount = $aircraftCount = $seatCount = 0;
    $output = [];
    $findAirport = $db->prepare('SELECT id FROM airports WHERE iata_code = ? FOR UPDATE');
    $addAirport = $db->prepare('INSERT INTO airports (iata_code, name, city, country, timezone) VALUES (?, ?, ?, ?, ?)');
    foreach ($airports as $airport) {
        $findAirport->execute([$airport[0]]);
        if ($findAirport->fetchColumn() === false) { $addAirport->execute($airport); ++$airportCount; }
    }
    // Sample registrations identify these aircraft across repeated runs.
    $fleet = [
        ['AP-SRA', 'Airbus A320', 150, 3, 23],
        ['AP-SRB', 'Boeing 737-800', 160, 4, 24],
        ['AP-SRC', 'Airbus A321', 200, 5, 30],
    ];
    $findAircraft = $db->prepare('SELECT * FROM aircraft WHERE registration_number = ? FOR UPDATE');
    $addAircraft = $db->prepare('INSERT INTO aircraft (registration_number, model, total_capacity) VALUES (?, ?, ?)');
    $findSeats = $db->prepare('SELECT seat_number, cabin_class FROM seats WHERE aircraft_id = ? FOR UPDATE');
    $addSeat = $db->prepare('INSERT INTO seats (aircraft_id, seat_number, cabin_class) VALUES (?, ?, ?)');
    foreach ($fleet as [$registration, $model, $capacity, $businessRows, $economyRows]) {
        $findAircraft->execute([$registration]);
        $aircraft = $findAircraft->fetch();
        if (!$aircraft) {
            $addAircraft->execute([$registration, $model, $capacity]);
            $aircraft = ['id' => (int) $db->lastInsertId(), 'model' => $model, 'total_capacity' => $capacity];
            ++$aircraftCount;
        }
        if ($aircraft['model'] !== $model) throw new RuntimeException($registration . ' belongs to a different aircraft model. No inventory changes saved.');
        $findSeats->execute([$aircraft['id']]);
        $existing = array_column($findSeats->fetchAll(), 'cabin_class', 'seat_number');
        $missing = [];
        for ($row = 1; $row <= $businessRows + $economyRows; ++$row) {
            $class = $row <= $businessRows ? 'business' : 'economy';
            foreach (str_split($class === 'business' ? 'ACDF' : 'ABCDEF') as $letter) {
                $number = $row . $letter;
                if (isset($existing[$number])) {
                    if ($existing[$number] !== $class) throw new RuntimeException($registration . ' seat ' . $number . ' has a conflicting class. No inventory changes saved.');
                } else $missing[$number] = $class;
            }
        }
        if (count($existing) + count($missing) > (int) $aircraft['total_capacity']) {
            throw new RuntimeException($registration . ' has insufficient capacity for the seed layout and existing seats. No inventory changes saved.');
        }
        foreach ($missing as $number => $class) $addSeat->execute([$aircraft['id'], $number, $class]);
        $seatCount += count($missing);
        $output[] = $registration . ' / ' . $model . ': ' . count($missing) . ' seats inserted; capacity ' . $aircraft['total_capacity'] . '.';
    }
    $db->commit();
    echo 'Inserted: ' . $airportCount . ' airports, ' . $aircraftCount . ' aircraft, ' . $seatCount . ' seats.' . PHP_EOL;
    echo implode(PHP_EOL, $output) . PHP_EOL;
    echo 'Existing matching records were preserved. No other tables were seeded.' . PHP_EOL;
} catch (Throwable $exception) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log((string) $exception);
    fwrite(STDERR, 'Inventory seed failed: ' . ($exception instanceof RuntimeException && !$exception instanceof PDOException ? $exception->getMessage() : 'Check database configuration and server logs.') . PHP_EOL);
    $failed = true;
} finally {
    if ($locked && $db instanceof PDO) {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    }
}
exit(isset($failed) ? 1 : 0);
