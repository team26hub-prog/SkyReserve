<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class FoundationController extends Controller
{
    public function index(): void
    {
        $this->render('foundation/index', ['title' => 'Airplane Ticketing System']);
    }
}
