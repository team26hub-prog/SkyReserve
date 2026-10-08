<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use App\Core\Database;
use App\Models\Flight;

$db = null;
$locked = false;
$failed = false;
try {
    require dirname(__DIR__) . '/bootstrap.php';
    $start = new DateTimeImmutable('tomorrow', new DateTimeZone('UTC'));
    $db = Database::connection();
    $lockName = 'skyreserve_flights_' . substr(hash('sha256', (string) $db->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    $lock = $db->prepare('SELECT GET_LOCK(?, 15)'); $lock->execute([$lockName]);
    if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Another flight seed is running. Try again shortly.');
    $locked = true;
    $db->beginTransaction();
    $airports = $db->query("SELECT iata_code, id FROM airports WHERE iata_code IN ('LHE','KHI','ISB','DXB','DOH','JED') AND status = 'active' FOR SHARE")->fetchAll(PDO::FETCH_KEY_PAIR);
    $aircraft = $db->query("SELECT registration_number, id FROM aircraft WHERE registration_number IN ('AP-SRA','AP-SRB','AP-SRC') AND status = 'active' FOR SHARE")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (count($airports) !== 6 || count($aircraft) !== 3) throw new RuntimeException('Run seed_inventory.php first and ensure its airports and aircraft are active.');
    $plan = [
        ['SR901','LHE','KHI','AP-SRA',0,'06:00',105,'18500.00'],
        ['SR902','KHI','LHE','AP-SRA',0,'09:00',105,'18500.00'],
        ['SR903','ISB','KHI','AP-SRB',0,'07:00',120,'22000.00'],
        ['SR904','KHI','ISB','AP-SRB',0,'10:15',120,'22000.00'],
        ['SR905','LHE','DXB','AP-SRC',1,'05:30',210,'68000.00'],
        ['SR906','DXB','LHE','AP-SRC',1,'10:30',195,'68000.00'],
        ['SR907','KHI','DOH','AP-SRA',2,'06:00',180,'72000.00'],
        ['SR908','DOH','KHI','AP-SRA',2,'10:30',165,'72000.00'],
        ['SR909','ISB','JED','AP-SRC',3,'03:00',330,'95000.00'],
        ['SR910','JED','ISB','AP-SRC',3,'10:00',300,'95000.00'],
    ];
    $find = $db->prepare('SELECT * FROM flights WHERE flight_number = ? FOR UPDATE');
    $inserted = 0; $output = [];
    foreach ($plan as [$number,$from,$to,$registration,$day,$time,$minutes,$fare]) {
        $find->execute([$number]); $existing = $find->fetchAll();
        if ($existing) {
            if (count($existing) !== 1 || (int) $existing[0]['origin_airport_id'] !== (int) $airports[$from]
                || (int) $existing[0]['destination_airport_id'] !== (int) $airports[$to]
                || (int) $existing[0]['aircraft_id'] !== (int) $aircraft[$registration]) {
                throw new RuntimeException($number . ' conflicts with existing flight data. No flights saved.');
            }
            continue; // Preserve existing dates, fares, statuses, and downstream records.
        }
        $departure = $start->modify('+' . $day . ' days')->setTime((int) substr($time,0,2),(int) substr($time,3,2));
        $arrival = $departure->modify('+' . $minutes . ' minutes');
        (new Flight())->save([
            'flight_number' => $number, 'origin_airport_id' => $airports[$from], 'destination_airport_id' => $airports[$to],
            'aircraft_id' => $aircraft[$registration], 'departure_at' => $departure->format('Y-m-d H:i:s'),
            'arrival_at' => $arrival->format('Y-m-d H:i:s'), 'base_fare' => $fare, 'status' => 'scheduled',
        ]);
        ++$inserted;
        $output[] = "$number $from -> $to | $registration | " . $departure->format('Y-m-d H:i') . ' -> ' . $arrival->format('Y-m-d H:i') . " UTC | PKR $fare | Scheduled";
    }
    $db->commit();
    echo "$inserted future Scheduled flights inserted; existing sample flights preserved." . PHP_EOL;
    if ($output) echo implode(PHP_EOL,$output) . PHP_EOL;
} catch (Throwable $exception) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log((string) $exception);
    fwrite(STDERR, 'Flight seed failed: ' . ($exception instanceof RuntimeException && !$exception instanceof PDOException ? $exception->getMessage() : 'Check database configuration and server logs.') . PHP_EOL);
    $failed = true;
} finally {
    if ($locked && $db instanceof PDO) { $release = $db->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]); }
}
exit($failed ? 1 : 0);
