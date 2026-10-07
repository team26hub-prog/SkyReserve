<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Airport;
use App\Models\Aircraft;
use App\Models\Flight;
use DateTimeImmutable;
use DateTimeZone;
use OutOfBoundsException;
use PDOException;

final class FlightController extends AdminResourceController
{
    public function index(): void
    {
        $this->listing();
    }

    public function create(): void
    {
        $this->form(['status' => 'scheduled']);
    }

    public function edit(): void
    {
        $this->form($this->record());
    }

    public function show(): void
    {
        $this->render('admin/flights/show', ['title' => 'Flight details', 'flight' => $this->record()]);
    }

    public function store(): void
    {
        $this->save();
    }

    public function update(): void
    {
        $this->save($this->id());
    }

    public function delete(): void
    {
        $this->requireCsrf();
        try {
            (new Flight())->delete($this->id());
        } catch (OutOfBoundsException) {
            $this->notFound();
        } catch (PDOException $exception) {
            http_response_code(409);
            $this->listing($this->constraintMessage($exception, 'A flight with this number and departure time already exists.'));
            return;
        }
        Session::flash('Flight deleted.');
        $this->redirect('/admin/flights');
    }

    private function save(?int $id = null): void
    {
        $this->requireCsrf();
        $model = new Flight();
        $existing = $id !== null ? $model->find($id) : null;
        if ($id !== null && !$existing) {
            $this->notFound();
        }
        [$values, $errors] = $this->textFields(['flight_number' => ['Flight number', 12]]);
        $values['flight_number'] = strtoupper($values['flight_number']);
        if (!preg_match('/\A[A-Z0-9][A-Z0-9-]{0,11}\z/', $values['flight_number'])) {
            $errors['flight_number'] = 'Use 1–12 letters, digits, or hyphens for the flight number.';
        }

        foreach (['origin_airport_id' => ['Departure airport', new Airport()], 'destination_airport_id' => ['Arrival airport', new Airport()], 'aircraft_id' => ['Aircraft', new Aircraft()]] as $key => [$label, $reference]) {
            $values[$key] = $this->input($key);
            $referenceId = filter_var($values[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($referenceId === false || !$reference->find($referenceId)) {
                $errors[$key] = 'Select an existing ' . strtolower($label) . '.';
            }
        }
        if (!isset($errors['origin_airport_id']) && !isset($errors['destination_airport_id'])
            && (int) $values['origin_airport_id'] === (int) $values['destination_airport_id']) {
            $errors['destination_airport_id'] = 'Departure and arrival airports must be different.';
        }

        $dates = [];
        foreach (['departure_at' => 'Departure date/time', 'arrival_at' => 'Arrival date/time'] as $key => $label) {
            $values[$key] = $this->input($key);
            $dates[$key] = $this->date($values[$key]);
            if (!$dates[$key]) {
                $errors[$key] = $label . ' must be a valid UTC date/time.';
            }
        }
        if ($dates['departure_at'] && $dates['arrival_at'] && $dates['arrival_at'] <= $dates['departure_at']) {
            $errors['arrival_at'] = 'Arrival time must be after departure time.';
        }

        $values['base_fare'] = $this->input('base_fare');
        // Validate decimal strings exactly; never round a floating-point fare.
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/', $values['base_fare'])
            || ltrim(str_replace('.', '', $values['base_fare']), '0') === '') {
            $errors['base_fare'] = 'Fare must be positive, with at most two decimal places (maximum 9999999999.99).';
        }
        $values['status'] = $this->input('status');
        if (!array_key_exists($values['status'], Flight::STATUSES)) {
            $errors['status'] = 'Select Scheduled, Delayed, Cancelled, or Completed.';
        }

        if (!$errors) {
            $stored = $values;
            foreach ($dates as $key => $date) {
                $stored[$key] = $date->format('Y-m-d H:i:s');
            }
            [$whole, $fraction] = array_pad(explode('.', $stored['base_fare']), 2, '');
            $stored['base_fare'] = $whole . '.' . str_pad($fraction, 2, '0');
            try {
                $model->save($stored, $id);
            } catch (OutOfBoundsException) {
                $this->notFound();
            } catch (PDOException $exception) {
                $errors['record'] = match ((int) ($exception->errorInfo[1] ?? 0)) {
                    1062 => 'A flight with this number and departure time already exists.',
                    1452 => 'A selected airport or aircraft no longer exists. Select valid references and try again.',
                    1451 => 'This flight is in use; its aircraft cannot be changed.',
                    default => throw $exception,
                };
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->form($values + ['id' => $id, 'currency' => $existing['currency'] ?? 'PKR'], $errors);
            return;
        }
        Session::flash($id === null ? 'Flight added.' : 'Flight updated.');
        $this->redirect('/admin/flights');
    }

    private function date(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}(?::[0-9]{2})?\z/', $value) || (int) substr($value, 0, 4) < 1000) {
            return null;
        }
        $format = strlen($value) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format($format) === $value ? $date : null;
    }

    private function record(): array
    {
        $record = (new Flight())->find($this->id());
        if (!$record) {
            $this->notFound();
        }
        return $record;
    }

    private function listing(?string $error = null): void
    {
        $this->render('admin/flights/index', ['title' => 'Flights', 'flights' => (new Flight())->all(), 'error' => $error, 'wide' => true]);
    }

    private function form(array $record = [], array $errors = []): void
    {
        $this->render('admin/flights/form', ['title' => empty($record['id']) ? 'Add flight' : 'Edit flight', 'record' => $record, 'errors' => $errors,
            'airports' => (new Airport())->all(), 'aircraft' => (new Aircraft())->all(), 'statuses' => Flight::STATUSES]);
    }
}
