<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\PaymentReceipt;
use App\Core\Session;
use App\Models\Payment;
use App\Models\Passenger;
use App\Models\Ticket;
use DomainException;
use OutOfBoundsException;
use Throwable;

final class AdminPaymentController extends Controller
{
    public function __construct()
    {
        $this->requireRole('admin');
    }

    public function index(): void
    {
        $status = $_GET['status'] ?? 'pending';
        $error = null;
        if (!is_string($status) || !isset(Payment::REVIEW_STATUSES[$status])) {
            http_response_code(422);
            $error = 'Choose Pending, Verified, or Rejected.';
            $status = 'pending';
        }
        $this->render('admin/payments/index', [
            'title' => 'Payments', 'wide' => true, 'status' => $status, 'error' => $error,
            'statuses' => Payment::REVIEW_STATUSES, 'payments' => (new Payment())->listForAdmin($status),
        ]);
    }

    public function show(): void
    {
        $this->details($this->id());
    }

    public function verify(): void
    {
        $this->review('verified');
    }

    public function reject(): void
    {
        $this->review('rejected');
    }

    private function review(string $decision): void
    {
        $this->requireCsrf();
        $user = $this->requireRole('admin');
        $id = $this->id();
        $reason = $decision === 'rejected' ? ($_POST['reason'] ?? '') : '';
        if (!is_string($reason) || !Payment::validReviewReason($reason)) {
            http_response_code(422);
            $this->details($id, 'Enter a valid rejection reason of at most 1000 characters.');
            return;
        }
        $reason = trim($reason);
        try {
            (new Payment())->review($id, (int) $user['id'], $decision, $reason);
        } catch (OutOfBoundsException) {
            $this->missing();
            return;
        } catch (DomainException $exception) {
            http_response_code(409);
            $this->details($id, $exception->getMessage(), $reason);
            return;
        } catch (Throwable $exception) {
            error_log((string) $exception);
            http_response_code(503);
            $this->details($id, 'The payment review could not be saved. Please try again.', $reason);
            return;
        }
        Session::flash($decision === 'verified' ? 'Payment verified. The booking is confirmed.' : 'Payment rejected. The booking requires payment again.');
        $this->redirect('/admin/payments/show?id=' . $id);
    }

    private function details(int $id, ?string $error = null, string $reason = ''): void
    {
        $payment = (new Payment())->findForAdmin($id);
        if (!$payment) { $this->missing(); return; }
        $this->render('admin/payments/show', [
            'title' => 'Payment details', 'payment' => $payment, 'error' => $error, 'reason' => $reason,
            'passengers' => (new Passenger())->forBooking((int) $payment['booking_id']),
            'tickets' => (new Ticket())->forBooking((int) $payment['booking_id']),
        ]);
    }

    public function receipt(): void
    {
        $payment = (new Payment())->findForAdmin($this->id());
        if (!$payment || !$payment['proof_path'] || !(new PaymentReceipt())->stream($payment['proof_path'])) $this->missing();
    }

    private function id(): int
    {
        $raw = $_GET['id'] ?? null;
        $id = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) { $this->missing(); exit; }
        return $id;
    }

    private function missing(): void
    {
        http_response_code(404);
        $this->render('admin/payments/error', ['title' => 'Payment not found']);
    }
}
