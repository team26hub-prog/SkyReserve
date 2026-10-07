<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDOException;

abstract class AdminResourceController extends Controller
{
    public function __construct()
    {
        $this->requireRole('admin');
    }

    protected function id(string $key = 'id'): int
    {
        $raw = $_GET[$key] ?? null;
        $id = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) {
            $this->notFound();
        }
        return $id;
    }

    protected function notFound(): never
    {
        http_response_code(404);
        $this->render('errors/not_found', ['title' => 'Record not found']);
        exit;
    }

    protected function input(string $key): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    }

    protected function textFields(array $limits): array
    {
        $values = $errors = [];
        foreach ($limits as $key => [$label, $max]) {
            $value = $this->input($key);
            $values[$key] = $value;
            if ($value === '' || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                $errors[$key] = $label . ' is required and must be at most ' . $max . ' characters.';
            }
        }
        return [$values, $errors];
    }

    protected function constraintMessage(PDOException $exception, string $duplicate): string
    {
        return match ((int) ($exception->errorInfo[1] ?? 0)) {
            1062 => $duplicate,
            1451 => 'This record is in use and cannot be deleted. Remove its related records first.',
            default => throw $exception,
        };
    }
}
