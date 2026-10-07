<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Aircraft;
use DomainException;
use OutOfBoundsException;
use PDOException;

final class AircraftController extends AdminResourceController
{
    public function index(): void
    {
        $this->listing();
    }

    public function create(): void
    {
        $this->form();
    }

    public function edit(): void
    {
        $record = (new Aircraft())->find($this->id());
        if (!$record) {
            $this->notFound();
        }
        $this->form($record);
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
            (new Aircraft())->delete($this->id());
        } catch (OutOfBoundsException) {
            $this->notFound();
        } catch (DomainException | PDOException $exception) {
            http_response_code(409);
            $this->listing($exception instanceof PDOException ? $this->constraintMessage($exception, 'Registration number already exists.') : $exception->getMessage());
            return;
        }
        Session::flash('Aircraft deleted.');
        $this->redirect('/admin/aircraft');
    }

    private function save(?int $id = null): void
    {
        $this->requireCsrf();
        $model = new Aircraft();
        if ($id !== null && !$model->find($id)) {
            $this->notFound();
        }
        [$values, $errors] = $this->textFields(['model' => ['Aircraft name/model', 100], 'registration_number' => ['Registration number', 20]]);
        $values['registration_number'] = strtoupper($values['registration_number']);
        if (!preg_match('/\A[A-Z0-9][A-Z0-9-]{0,19}\z/', $values['registration_number'])) {
            $errors['registration_number'] = 'Use 1–20 letters, digits, or hyphens for the registration number.';
        }
        $values['total_capacity'] = $this->input('total_capacity');
        $capacity = filter_var($values['total_capacity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
        if ($capacity === false) {
            $errors['total_capacity'] = 'Total capacity must be a positive whole number (maximum 4294967295).';
        }
        if (!$errors) {
            try {
                $model->save($values, $id);
            } catch (OutOfBoundsException) {
                $this->notFound();
            } catch (DomainException | PDOException $exception) {
                $errors['record'] = $exception instanceof PDOException ? $this->constraintMessage($exception, 'Registration number already exists.') : $exception->getMessage();
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->form($values + ['id' => $id], $errors);
            return;
        }
        Session::flash($id === null ? 'Aircraft added.' : 'Aircraft updated.');
        $this->redirect('/admin/aircraft');
    }

    private function listing(?string $error = null): void
    {
        $this->render('admin/aircraft/index', ['title' => 'Aircraft', 'aircraft' => (new Aircraft())->all(), 'error' => $error, 'wide' => true]);
    }

    private function form(array $record = [], array $errors = []): void
    {
        $this->render('admin/aircraft/form', ['title' => empty($record['id']) ? 'Add aircraft' : 'Edit aircraft', 'record' => $record, 'errors' => $errors]);
    }
}
