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
    $data = ['user' => $role ? ['role' => $role] : null];
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    try { require BASE_PATH . '/app/Views/foundation/index.php'; return ob_get_contents(); }
    finally { ob_end_clean(); }
};
$renderSearch = static function (array $flights, ?string $role, bool $submitted = false): string {
    $data = ['user' => $role ? ['role' => $role] : null, 'upcomingFlights' => $flights, 'flights' => [], 'submitted' => $submitted, 'airports' => [], 'values' => ['from_airport_id' => '', 'to_airport_id' => '', 'travel_date' => ''], 'errors' => [], 'today' => gmdate('Y-m-d')];
    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ob_start();
    try { require BASE_PATH . '/app/Views/customer/flights/search.php'; return ob_get_contents(); }
    finally { ob_end_clean(); }
};
try {
    $model = new Flight();
    $flights = $model->upcomingAvailable();
    $assert(count($flights) <= 6, 'Upcoming preview retains its six-flight limit');
    $expected = [];
    foreach (Database::connection()->query('SELECT id FROM flights ORDER BY departure_at, flight_number, id')->fetchAll() as $row) {
        if ($available = $model->findAvailable((int) $row['id'])) $expected[] = $available;
        if (count($expected) === 6) break;
    }
    $assert(array_column($flights, 'id') === array_column($expected, 'id'), 'Upcoming preview uses the same earliest eligible flights');
    foreach ($flights as $flight) {
        $assert($flight['status'] === 'scheduled' && $flight['departure_at'] > gmdate('Y-m-d H:i:s') && (int) $flight['available_seats'] > 0, 'Flight is future, scheduled, and available');
        $assert((int) $flight['available_seats'] === (int) $model->findAvailable((int) $flight['id'])['available_seats'], 'Seat availability matches customer details');
    }
    foreach ([null => '/login', 'customer' => '/bookings', 'admin' => '/admin'] as $role => $destination) {
        foreach ([$flights, []] as $records) {
            $html = $render($records, $role ?: null);
            $assert(!str_contains($html, 'upcoming-title') && !str_contains($html, 'data-upcoming-flight='), 'Home contains no upcoming-flight section');
            $searchHtml = $renderSearch($records, $role ?: null);
            $assert(substr_count($searchHtml, 'data-upcoming-flight=') === count($records), 'Search preview card count matches existing model data');
            $assert(str_contains($searchHtml, 'data-upcoming-empty') === !$records, 'Search empty state appears only without upcoming flights');
            $assert(strpos($searchHtml, 'search-form') < strpos($searchHtml, 'upcoming-title'), 'Upcoming flights appear below the search form');
            $submittedHtml = $renderSearch($records, $role ?: null, true);
            $assert(str_contains($submittedHtml, 'Search results') && !str_contains($submittedHtml, 'upcoming-title'), 'Submitted search replaces upcoming preview with results');
            $assert(str_contains(substr($html, strpos($html, 'home-final-cta')), 'href="' . $destination . '"'), 'Final CTA respects the visitor role');
            $assert(substr_count($html, 'class="card benefit-card"') === 4 && substr_count($html, '<li>') === 6, 'Four benefits and six journey steps render');
            $assert(str_contains($html, 'From plans to takeoff') && str_contains($html, 'welcome-title'), 'Existing homepage sections are preserved');
        }
    }
    if ($flights) {
        $flights[0]['origin_city'] = '<script>alert(1)</script>';
        $html = $renderSearch($flights, null);
        $assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Database content is escaped');
    }
    echo "$checks homepage checks passed. Database unchanged." . PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, 'Homepage test failed: ' . $exception->getMessage() . PHP_EOL); exit(1); }
