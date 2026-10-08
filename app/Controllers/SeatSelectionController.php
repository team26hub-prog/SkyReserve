<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\BookingSeat;
use DomainException;
use OutOfBoundsException;

final class SeatSelectionController extends Controller
{
    public function __construct()
    {
        $this->requireRole('customer');
    }

    public function index(): void
    {
        $this->page();
    }

    public function store(): void
    {
        $this->requireCsrf();
        $user = $this->requireRole('customer');
        $bookingId = $this->bookingId();
        $passengerId = $this->positive($_POST['passenger_id'] ?? null);
        $seatId = $this->positive($_POST['seat_id'] ?? null);
        if ($passengerId === null || $seatId === null) {
            http_response_code(422);
            $this->page('Choose a passenger and an available seat.');
            return;
        }
        try {
            (new BookingSeat())->assign($bookingId, (int) $user['id'], $passengerId, $seatId);
        } catch (OutOfBoundsException) {
            $this->missing();
            return;
        } catch (DomainException $exception) {
            http_response_code(409);
            $this->page($exception->getMessage());
            return;
        }
        Session::flash('Seat selected successfully.');
        $this->redirect('/bookings/show?id=' . $bookingId);
    }

    private function page(?string $error = null): void
    {
        $user = $this->requireRole('customer');
        $id = $this->bookingId();
        $model = new BookingSeat();
        try {
            $context = $model->context($id, (int) $user['id']);
        } catch (OutOfBoundsException) {
            $this->missing();
            return;
        } catch (DomainException $exception) {
            http_response_code(409);
            $this->render('customer/bookings/error', ['title' => 'Seat selection unavailable', 'message' => $exception->getMessage()]);
            return;
        }
        $this->render('customer/bookings/seats', $context + [
            'title' => 'Select a seat',
            'error' => $error,
            'seats' => $model->map((int) $context['flight']['aircraft_id'], (int) $context['flight']['id']),
            'assignments' => $model->forBooking($id),
        ]);
    }

    private function positive(mixed $raw): ?int
    {
        $id = is_string($raw) ? filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        return $id === false ? null : $id;
    }

    private function bookingId(): int
    {
        $id = $this->positive($_GET['booking_id'] ?? null);
        if ($id === null) {
            $this->missing();
            exit;
        }
        return $id;
    }

    private function missing(): void
    {
        http_response_code(404);
        $this->render('customer/bookings/error', ['title' => 'Booking not found', 'message' => 'This booking does not exist or is not accessible.']);
    }
}
