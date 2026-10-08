<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Ticket;
use DomainException;
use OutOfBoundsException;
use Throwable;

final class TicketController extends Controller
{
    public function show(): void { $this->view('customer'); }
    public function adminShow(): void { $this->view('admin'); }
    public function download(): void { $this->view('customer', true); }
    public function adminDownload(): void { $this->view('admin', true); }
    public function generate(): void { $this->issue('customer'); }
    public function adminGenerate(): void { $this->issue('admin'); }

    private function issue(string $role): void
    {
        $user = $this->requireRole($role);
        $this->requireCsrf();
        try { $ids = (new Ticket())->generate($this->id('booking_id'), $user); }
        catch (OutOfBoundsException) { $this->error(404, 'The booking does not exist or is not accessible.'); return; }
        catch (DomainException $exception) { $this->error(409, $exception->getMessage()); return; }
        catch (Throwable $exception) { error_log((string) $exception); $this->error(503, 'The ticket could not be saved. Please try again.'); return; }
        Session::flash('Ticket generated successfully.');
        $this->redirect(($role === 'admin' ? '/admin' : '') . '/tickets/show?id=' . $ids[0]);
    }

    private function view(string $role, bool $download = false): void
    {
        $user = $this->requireRole($role);
        $ticket = (new Ticket())->findAuthorized($this->id('id'), $user);
        if (!$ticket) { $this->error(404, 'The ticket does not exist or is not accessible.'); return; }
        if ($download) {
            // A self-contained document works offline and can be printed / saved as PDF.
            $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="SkyReserve-ticket-' . (int) $ticket['id'] . '.html"');
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'");
            require BASE_PATH . '/app/Views/tickets/download.php';
            return;
        }
        $this->render($role . '/tickets/show', ['title' => 'Ticket ' . $ticket['ticket_number'], 'ticket' => $ticket, 'ticketPrefix' => $role === 'admin' ? '/admin' : '']);
    }

    private function id(string $key): int
    {
        $raw = $_GET[$key] ?? null;
        $id = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) { $this->error(404, 'The requested ticket or booking was not found.'); exit; }
        return $id;
    }

    private function error(int $status, string $message): void
    {
        http_response_code($status);
        $this->render('tickets/error', ['title' => 'Ticket unavailable', 'message' => $message]);
    }
}
