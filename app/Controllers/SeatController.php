<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Aircraft;
use App\Models\Seat;
use DomainException;
use OutOfBoundsException;
use PDOException;

final class SeatController extends AdminResourceController
{
    public function index(): void
    {
        $this->listing($this->aircraft());
    }

    public function create(): void
    {
        $this->form($this->aircraft());
    }

    public function edit(): void
    {
        $aircraft = $this->aircraft();
        $record = (new Seat())->find($this->id(), (int) $aircraft['id']);
        if (!$record) {
            $this->notFound();
        }
        $this->form($aircraft, $record);
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
        $aircraft = $this->aircraft();
        try {
            (new Seat())->delete($this->id(), (int) $aircraft['id']);
        } catch (OutOfBoundsException) {
            $this->notFound();
        } catch (PDOException $exception) {
            http_response_code(409);
            $this->listing($aircraft, $this->constraintMessage($exception, 'Seat number already exists on this aircraft.'));
            return;
        }
        Session::flash('Seat deleted.');
        $this->redirect('/admin/seats?aircraft_id=' . $aircraft['id']);
    }

    private function save(?int $id = null): void
    {
        $this->requireCsrf();
        $aircraft = $this->aircraft();
        $aircraftId = (int) $aircraft['id'];
        $model = new Seat();
        if ($id !== null && !$model->find($id, $aircraftId)) {
            $this->notFound();
        }
        [$values, $errors] = $this->textFields(['seat_number' => ['Seat number', 8]]);
        $values['seat_number'] = strtoupper($values['seat_number']);
        if (!preg_match('/\A[A-Z0-9][A-Z0-9-]{0,7}\z/', $values['seat_number'])) {
            $errors['seat_number'] = 'Use 1–8 letters, digits, or hyphens for the seat number.';
        }
        $values['cabin_class'] = $this->input('cabin_class');
        if (!in_array($values['cabin_class'], ['economy', 'business'], true)) {
            $errors['cabin_class'] = 'Select Economy or Business.';
        }
        if (!$errors) {
            try {
                $model->save($aircraftId, $values, $id);
            } catch (OutOfBoundsException) {
                $this->notFound();
            } catch (DomainException | PDOException $exception) {
                $errors['record'] = $exception instanceof PDOException ? $this->constraintMessage($exception, 'Seat number already exists on this aircraft.') : $exception->getMessage();
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->form($aircraft, $values + ['id' => $id], $errors);
            return;
        }
        Session::flash($id === null ? 'Seat added.' : 'Seat updated.');
        $this->redirect('/admin/seats?aircraft_id=' . $aircraftId);
    }

    private function aircraft(): array
    {
        $record = (new Aircraft())->find($this->id('aircraft_id'));
        if (!$record) {
            $this->notFound();
        }
        return $record;
    }

    private function listing(array $aircraft, ?string $error = null): void
    {
        $this->render('admin/seats/index', ['title' => 'Aircraft seats', 'aircraft' => $aircraft, 'seats' => (new Seat())->allForAircraft((int) $aircraft['id']), 'error' => $error, 'wide' => true]);
    }

    private function form(array $aircraft, array $record = [], array $errors = []): void
    {
        $this->render('admin/seats/form', ['title' => empty($record['id']) ? 'Add seat' : 'Edit seat', 'aircraft' => $aircraft, 'record' => $record, 'errors' => $errors]);
    }
}
