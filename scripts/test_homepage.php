<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Models\Flight;

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) throw new RuntimeException($label);
    ++$checks;
    echo 'PASS: ' . $label . PHP_EOL;
};
$render = static function (array $flights, ?string $role): string {
    $data = ['upcomingFlights' => $flights, 'user' => $role ? ['role' => $role] : null];
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    try { require BASE_PATH . '/app/Views/foundation/index.php'; return ob_get_contents(); }
    finally { ob_end_clean(); }
};
try {
    $model = new Flight();
    $flights = $model->upcomingAvailable();
    $assert(count($flights) <= 6, 'Homepage is limited to six flights');
    $expected = [];
    foreach (Database::connection()->query('SELECT id FROM flights ORDER BY departure_at, flight_number, id')->fetchAll() as $row) {
        if ($available = $model->findAvailable((int) $row['id'])) $expected[] = $available;
        if (count($expected) === 6) break;
    }
    $assert(array_column($flights, 'id') === array_column($expected, 'id'), 'Homepage uses the earliest flights eligible for customer search');
    foreach ($flights as $flight) {
        $assert($flight['status'] === 'scheduled' && $flight['departure_at'] > gmdate('Y-m-d H:i:s') && (int) $flight['available_seats'] > 0, 'Flight is future, scheduled, and available');
        $assert((int) $flight['available_seats'] === (int) $model->findAvailable((int) $flight['id'])['available_seats'], 'Seat availability matches customer details');
    }
    foreach ([null => '/login', 'customer' => '/bookings', 'admin' => '/admin'] as $role => $destination) {
        foreach ([$flights, []] as $records) {
            $html = $render($records, $role ?: null);
            $assert(substr_count($html, 'data-upcoming-flight=') === count($records), 'Flight card count matches model data');
            $assert(str_contains($html, 'data-upcoming-empty') === !$records, 'Empty state appears only without flights');
            $assert(str_contains(substr($html, strpos($html, 'home-final-cta')), 'href="' . $destination . '"'), 'Final CTA respects the visitor role');
            $assert(substr_count($html, 'class="card benefit-card"') === 4 && substr_count($html, '<li>') === 6, 'Four benefits and six journey steps render');
            $assert(str_contains($html, 'From plans to takeoff') && str_contains($html, 'welcome-title'), 'Existing homepage sections are preserved');
        }
    }
    if ($flights) {
        $flights[0]['origin_city'] = '<script>alert(1)</script>';
        $html = $render($flights, null);
        $assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Database content is escaped');
    }
    echo "$checks homepage checks passed. Database unchanged." . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, 'Homepage test failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
