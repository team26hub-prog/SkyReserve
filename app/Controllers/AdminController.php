<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AdminReport;

final class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin');
        $metrics = (new AdminReport())->dashboard();
        $this->render('admin/index', ['title' => 'Admin area', 'wide' => true, 'metrics' => $metrics, 'pendingPayments' => $metrics['pending_payments'], 'pendingCancellations' => $metrics['pending_cancellations']]);
    }
}
