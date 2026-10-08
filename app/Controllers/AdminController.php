<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Payment;
use App\Models\Cancellation;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin');
        $this->render('admin/index', ['title' => 'Admin area', 'pendingPayments' => (new Payment())->pendingCount(), 'pendingCancellations' => (new Cancellation())->pendingCount()]);
    }
}
