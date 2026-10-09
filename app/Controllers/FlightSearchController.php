<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Airport;
use App\Models\Flight;
use DateTimeImmutable;
use DateTimeZone;

// Public, read-only customer browsing. Admin mutations retain their role guards.
final class FlightSearchController extends Controller
{
    public function index(): void
    {
        $airports = array_values(array_filter((new Airport())->all(), static fn (array $airport): bool => $airport['status'] === 'active'));
        $values = ['from_airport_id' => $this->input('from_airport_id'), 'to_airport_id' => $this->input('to_airport_id'), 'travel_date' => $this->input('travel_date')];
        $submitted = isset($_GET['from_airport_id']) || isset($_GET['to_airport_id']) || isset($_GET['travel_date']);
        $errors = $flights = [];
        if ($submitted) {
            $ids = array_column($airports, 'id');
            foreach (['from_airport_id' => 'From airport', 'to_airport_id' => 'To airport'] as $key => $label) {
                $id = filter_var($values[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false || !in_array($id, $ids)) {
                    $errors[$key] = $label . ': select an available airport.';
                }
            }
            if (!isset($errors['from_airport_id']) && !isset($errors['to_airport_id']) && (int) $values['from_airport_id'] === (int) $values['to_airport_id']) {
                $errors['to_airport_id'] = 'From and To airports must be different.';
            }
            $date = $this->date($values['travel_date']);
            if (!$date || $date < new DateTimeImmutable('today', new DateTimeZone('UTC'))) {
                $errors['travel_date'] = 'Choose a valid travel date today or later (UTC).';
            }
            if (!$errors) {
                $flights = (new Flight())->searchAvailable((int) $values['from_airport_id'], (int) $values['to_airport_id'], $values['travel_date']);
            } else {
                http_response_code(422);
            }
        }
        $this->render('customer/flights/search', ['title' => 'Search flights', 'airports' => $airports, 'values' => $values, 'errors' => $errors,
            'submitted' => $submitted, 'flights' => $flights, 'upcomingFlights' => $submitted ? [] : (new Flight())->upcomingAvailable(), 'today' => gmdate('Y-m-d'), 'wide' => true]);
    }

    public function show(): void
    {
        $id = filter_var($this->input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $flight = $id !== false ? (new Flight())->findAvailable($id) : null;
        if (!$flight) {
            http_response_code(404);
            $this->render('customer/flights/unavailable', ['title' => 'Flight unavailable']);
            return;
        }
        $this->render('customer/flights/show', ['title' => 'Flight ' . $flight['flight_number'], 'flight' => $flight,
            'back' => '/flights?' . http_build_query(['from_airport_id' => $flight['origin_airport_id'], 'to_airport_id' => $flight['destination_airport_id'], 'travel_date' => substr($flight['departure_at'], 0, 10)])]);
    }

    private function input(string $key): string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
    }

    private function date(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) || (int) substr($value, 0, 4) < 1000) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format('Y-m-d') === $value ? $date : null;
    }
}
