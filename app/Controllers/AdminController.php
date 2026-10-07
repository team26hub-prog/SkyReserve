<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin');
        $this->render('admin/index', ['title' => 'Admin area']);
    }
}
