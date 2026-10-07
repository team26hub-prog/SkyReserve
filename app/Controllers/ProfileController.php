<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ProfileController extends Controller
{
    public function index(): void
    {
        $this->requireRole('customer');
        $this->render('customer/profile', ['title' => 'Your profile']);
    }
}
