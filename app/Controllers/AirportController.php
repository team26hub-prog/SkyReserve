<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\Airport;
use PDOException;

final class AirportController extends AdminResourceController
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
        $record = (new Airport())->find($this->id());
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
        $id = $this->id();
        $model = new Airport();
        if (!$model->find($id)) {
            $this->notFound();
        }
        try {
            $model->delete($id);
        } catch (PDOException $exception) {
            http_response_code(409);
            $this->listing($this->constraintMessage($exception, 'Airport code already exists.'));
            return;
        }
        Session::flash('Airport deleted.');
        $this->redirect('/admin/airports');
    }

    private function save(?int $id = null): void
    {
        $this->requireCsrf();
        $model = new Airport();
        if ($id !== null && !$model->find($id)) {
            $this->notFound();
        }
        [$values, $errors] = $this->textFields(['iata_code' => ['Airport code', 3], 'name' => ['Airport name', 150], 'city' => ['City', 100], 'country' => ['Country', 100]]);
        $values['iata_code'] = strtoupper($values['iata_code']);
        if (!preg_match('/\A[A-Z]{3}\z/', $values['iata_code'])) {
            $errors['iata_code'] = 'Airport code must contain exactly three letters.';
        }
        if (!$errors) {
            try {
                $model->save($values, $id);
            } catch (PDOException $exception) {
                $errors['iata_code'] = $this->constraintMessage($exception, 'Airport code already exists.');
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->form($values + ['id' => $id], $errors);
            return;
        }
        Session::flash($id === null ? 'Airport added.' : 'Airport updated.');
        $this->redirect('/admin/airports');
    }

    private function listing(?string $error = null): void
    {
        $this->render('admin/airports/index', ['title' => 'Airports', 'airports' => (new Airport())->all(), 'error' => $error, 'wide' => true]);
    }

    private function form(array $record = [], array $errors = []): void
    {
        $this->render('admin/airports/form', ['title' => empty($record['id']) ? 'Add airport' : 'Edit airport', 'record' => $record, 'errors' => $errors]);
    }
}
