<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\PaymentReceipt;
use App\Core\Session;
use App\Models\Payment;
use DomainException;
use OutOfBoundsException;
use Throwable;

final class PaymentController extends Controller
{
    public function __construct()
    {
        $this->requireRole('customer');
    }

    public function create(): void
    {
        $context = $this->context();
        if ($context) $this->form($context);
    }

    public function store(): void
    {
        $this->requireCsrf();
        $user = $this->requireRole('customer');
        $context = $this->context();
        if (!$context) return;
        $values = [];
        foreach (['method', 'amount', 'transaction_reference', 'payment_date'] as $key) {
            $values[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
        }
        $errors = Payment::errors($values);
        if (isset($_FILES['receipt']) && !is_array($_FILES['receipt'])) $errors[] = 'Invalid receipt upload.';
        if ($errors) {
            http_response_code(422);
            $this->form($context, $values, $errors);
            return;
        }
        try {
            (new Payment())->submit((int) $context['booking']['id'], (int) $user['id'], $values, $_FILES['receipt'] ?? null);
        } catch (OutOfBoundsException) {
            $this->missing();
            return;
        } catch (DomainException $exception) {
            // A concurrent submission/state change may invalidate the page after it opened.
            try { (new Payment())->context((int) $context['booking']['id'], (int) $user['id']); }
            catch (DomainException $conflict) { $this->unavailable($conflict->getMessage(), (int) $context['booking']['id']); return; }
            catch (OutOfBoundsException) { $this->missing(); return; }
            http_response_code(422);
            $this->form($context, $values, [$exception->getMessage()]);
            return;
        } catch (Throwable $exception) {
            error_log((string) $exception);
            http_response_code(503);
            $this->form($context, $values, ['Your payment could not be saved. Please try again. If you attached a receipt, select it again.']);
            return;
        }
        Session::flash('Payment submitted. Your booking is awaiting verification and has not been confirmed.');
        $this->redirect('/bookings/show?id=' . (int) $context['booking']['id']);
    }

    public function receipt(): void
    {
        $user = $this->requireRole('customer');
        $payment = (new Payment())->receiptForCustomer($this->id('id'), (int) $user['id']);
        if (!$payment || !$payment['proof_path']) { $this->missing(); return; }
        if (!(new PaymentReceipt())->stream($payment['proof_path'])) $this->missing();
    }

    private function context(): ?array
    {
        $user = $this->requireRole('customer');
        $id = $this->id('booking_id');
        try { return (new Payment())->context($id, (int) $user['id']); }
        catch (OutOfBoundsException) { $this->missing(); }
        catch (DomainException $exception) { $this->unavailable($exception->getMessage(), $id); }
        return null;
    }

    private function form(array $context, array $values = [], array $errors = []): void
    {
        $this->render('customer/payments/form', $context + [
            'title' => 'Submit payment', 'values' => $values, 'errors' => $errors,
            'methods' => Payment::METHODS, 'today' => gmdate('Y-m-d'),
            'instructions' => require BASE_PATH . '/config/payments.php',
        ]);
    }

    private function id(string $key): int
    {
        $raw = $_GET[$key] ?? null;
        $id = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) { $this->missing(); exit; }
        return $id;
    }

    private function unavailable(string $message, int $bookingId): void
    {
        http_response_code(409);
        $this->render('customer/payments/error', ['title' => 'Payment unavailable', 'message' => $message, 'bookingId' => $bookingId]);
    }

    private function missing(): void
    {
        http_response_code(404);
        $this->render('customer/bookings/error', ['title' => 'Payment or booking not found', 'message' => 'This payment or booking does not exist or is not accessible.']);
    }
}
