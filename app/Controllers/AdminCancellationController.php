<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Cancellation;
use App\Models\Passenger;
use App\Models\Payment;
use App\Models\BookingSeat;
use App\Models\Ticket;
use DomainException;
use OutOfBoundsException;
use Throwable;

final class AdminCancellationController extends Controller
{
    public function __construct() { $this->requireRole('admin'); }
    public function index(): void
    {
        $status = $_GET['status'] ?? 'pending'; $error = null;
        if (!is_string($status) || !isset(Cancellation::STATUSES[$status])) { http_response_code(422); $status = 'pending'; $error = 'Choose Pending, Approved, or Rejected.'; }
        $this->render('admin/cancellations/index', ['title' => 'Cancellations', 'wide' => true, 'status' => $status, 'error' => $error, 'requests' => (new Cancellation())->listForAdmin($status)]);
    }
    public function show(): void { $this->details($this->id()); }
    public function approve(): void { $this->review('approved'); }
    public function reject(): void { $this->review('rejected'); }
    private function review(string $decision): void
    {
        $this->requireCsrf(); $user = $this->requireRole('admin'); $id = $this->id(); $note = $_POST['note'] ?? '';
        if (!is_string($note) || !Cancellation::validText($note)) { http_response_code(422); $this->details($id, 'Enter a valid admin note of at most 1000 characters.'); return; }
        try { (new Cancellation())->review($id, (int) $user['id'], $decision, $note); }
        catch (OutOfBoundsException) { $this->missing(); return; }
        catch (DomainException $exception) { http_response_code(409); $this->details($id, $exception->getMessage(), $note); return; }
        catch (Throwable $exception) { error_log((string) $exception); http_response_code(503); $this->details($id, 'The cancellation review could not be saved. Please try again.', $note); return; }
        Session::flash($decision === 'approved' ? 'Cancellation approved. Seats released and tickets voided. Payment records are unchanged.' : 'Cancellation rejected. The previous booking status has been restored.');
        $this->redirect('/admin/cancellations/show?id=' . $id);
    }
    private function details(int $id, ?string $error = null, string $note = ''): void
    {
        $request = (new Cancellation())->findForAdmin($id); if (!$request) { $this->missing(); return; }
        $bookingId = (int) $request['booking_id'];
        $this->render('admin/cancellations/show', ['title' => 'Cancellation details', 'request' => $request, 'error' => $error, 'note' => $note,
            'passengers' => (new Passenger())->forBooking($bookingId), 'payments' => (new Payment())->forBooking($bookingId),
            'assignments' => (new BookingSeat())->forBooking($bookingId), 'tickets' => (new Ticket())->forBooking($bookingId)]);
    }
    private function id(): int
    {
        $raw = $_GET['id'] ?? null; $id = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) { $this->missing(); exit; } return $id;
    }
    private function missing(): void { http_response_code(404); $this->render('admin/cancellations/error', ['title' => 'Cancellation not found', 'message' => 'The cancellation request was not found.']); }
}
