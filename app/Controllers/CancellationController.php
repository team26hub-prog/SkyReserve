<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\Cancellation;
use DomainException;
use OutOfBoundsException;
use Throwable;

final class CancellationController extends Controller
{
    public function __construct() { $this->requireRole('customer'); }
    public function create(): void { $this->form($this->id()); }
    public function store(): void
    {
        $this->requireCsrf(); $user = $this->requireRole('customer'); $id = $this->id();
        $reason = $_POST['reason'] ?? '';
        if (!is_string($reason) || !Cancellation::validText($reason)) {
            http_response_code(422); $this->form($id, '', 'Enter a valid reason of at most 1000 characters.'); return;
        }
        try { (new Cancellation())->request($id, (int) $user['id'], $reason); }
        catch (OutOfBoundsException) { $this->error(404, 'The booking does not exist or is not accessible.'); return; }
        catch (DomainException $exception) { $this->error(409, $exception->getMessage()); return; }
        catch (Throwable $exception) { error_log((string) $exception); $this->error(503, 'The cancellation request could not be saved. Please try again.'); return; }
        Session::flash('Cancellation requested. Your booking is awaiting admin review.');
        $this->redirect('/bookings/show?id=' . $id);
    }
    private function form(int $id, string $reason = '', ?string $error = null): void
    {
        $user = $this->requireRole('customer'); $booking = (new Booking())->findForCustomer($id, (int) $user['id']);
        if (!$booking) { $this->error(404, 'The booking does not exist or is not accessible.'); return; }
        $flight = (new Flight())->find((int) $booking['flight_id']);
        if (!Cancellation::eligible($booking, $flight)) { $this->error(409, 'Cancellation is unavailable for this booking or flight.'); return; }
        $this->render('customer/cancellations/form', ['title' => 'Request cancellation', 'booking' => $booking, 'flight' => $flight, 'reason' => $reason, 'error' => $error]);
    }
    private function id(): int
    {
        $raw = $_GET['booking_id'] ?? null; $id = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) { $this->error(404, 'Booking not found.'); exit; } return $id;
    }
    private function error(int $status, string $message): void
    {
        http_response_code($status); $this->render('customer/cancellations/error', ['title' => 'Cancellation unavailable', 'message' => $message]);
    }
}
