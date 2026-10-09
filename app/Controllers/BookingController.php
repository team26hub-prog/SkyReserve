<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Core\Session;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\Passenger;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Cancellation;
use DateTimeImmutable;
use DomainException;
final class BookingController extends Controller
{
    public function __construct() { $this->requireRole('customer'); }
    public function index(): void
    {
        $user = $this->requireRole('customer'); $status = $_GET['status'] ?? 'all'; $error = null;
        if (!is_string($status) || ($status !== 'all' && !isset(Booking::STATUSES[$status]))) { http_response_code(422); $status = 'all'; $error = 'Choose a valid booking status.'; }
        $sections = ['bookings' => 'My Bookings', 'seats' => 'My seats', 'payments' => 'My payments', 'tickets' => 'My E-tickets'];
        $section = $_GET['section'] ?? 'bookings';
        if (!is_string($section) || !isset($sections[$section])) { http_response_code(422); $section = 'bookings'; $error = 'Choose a valid booking section.'; }
        $this->render('customer/bookings/index', ['title' => $sections[$section], 'section' => $section, 'wide' => true, 'status' => $status, 'error' => $error, 'bookings' => (new Booking())->listForCustomer((int) $user['id'], $status)]);
    }
    public function create(): void
    {
        $flight = (new Flight())->findAvailable($this->id('flight_id'));
        if (!$flight) { $this->unavailable(); return; }
        $forms = $_SESSION['booking_forms'] ?? [];
        $forms = array_filter($forms, static fn (array $form): bool => $form['expires'] >= time());
        $forms = array_slice($forms, -19, null, true);
        $token = bin2hex(random_bytes(32));
        $forms[$token] = ['flight_id' => (int) $flight['id'], 'expires' => time() + 1800];
        $_SESSION['booking_forms'] = $forms;
        $this->form($flight, $token);
    }
    public function store(): void
    {
        $this->requireCsrf();
        $user = $this->requireRole('customer');
        $flightId = $this->id('flight_id');
        $token = $this->input('booking_token');
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) { $this->expired(); return; }
        $key = hash('sha256', $token);
        $model = new Booking();
        if ($existing = $model->submitted((int) $user['id'], $key)) {
            if ((int) $existing['flight_id'] !== $flightId) { $this->expired(); return; }
            $this->redirect('/bookings/show?id=' . $existing['id']);
        }
        $form = $_SESSION['booking_forms'][$token] ?? null;
        if (!$form || $form['expires'] < time() || $form['flight_id'] !== $flightId) { $this->expired(); return; }
        $flight = (new Flight())->findAvailable($flightId);
        if (!$flight) { $this->unavailable(); return; }
        $values = [];
        foreach (['full_name', 'document_number', 'date_of_birth', 'gender', 'phone'] as $field) { $values[$field] = $this->input($field); }
        $errors = [];
        if (!mb_check_encoding($values['full_name'], 'UTF-8') || mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 160 || !preg_match("/\A[\p{L}\p{M} .'-]+\z/u", $values['full_name']) || !preg_match('/\p{L}/u', $values['full_name'])) {
            $errors[] = 'Enter a full name of 2–160 characters using letters, spaces, apostrophes, periods, or hyphens.';
        }
        $document = strtoupper($values['document_number']);
        if (preg_match('/\A[0-9]{5}-[0-9]{7}-[0-9]\z/', $document)) { $document = str_replace('-', '', $document); }
        if (!preg_match('/\A(?:[0-9]{13}|(?=[A-Z0-9]{6,20}\z)(?=[A-Z0-9]*[A-Z])[A-Z0-9]+)\z/', $document)) { $errors[] = 'Enter a 13-digit CNIC or a 6–20 character alphanumeric passport number containing a letter.'; }
        $dob = preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $values['date_of_birth']) ? DateTimeImmutable::createFromFormat('!Y-m-d', $values['date_of_birth']) : false;
        if (!$dob || $dob->format('Y-m-d') !== $values['date_of_birth'] || $dob > new DateTimeImmutable('today') || (int) $dob->format('Y') < 1900) { $errors[] = 'Enter a valid date of birth from 1900 through today (UTC).'; }
        if (!in_array($values['gender'], ['male', 'female', 'other'], true)) { $errors[] = 'Select a gender.'; }
        $digits = preg_replace('/[^0-9]/', '', $values['phone']);
        if (strlen($values['phone']) > 30 || !preg_match('/\A\+?[0-9 ()-]+\z/', $values['phone']) || strlen($digits) < 7 || strlen($digits) > 15) { $errors[] = 'Enter a valid phone number with 7–15 digits.'; }
        if ($errors) { http_response_code(422); $this->form($flight, $token, $values, $errors); return; }
        $values['document_number'] = $document;
        try { $id = $model->create((int) $user['id'], $flightId, $key, $values); }
        catch (DomainException) { $this->unavailable(); return; }
        Session::flash('Booking created. Choose your seat and submit payment to continue.');
        $this->redirect('/bookings/show?id=' . $id);
    }
    public function show(): void
    {
        $user = $this->requireRole('customer');
        $booking = (new Booking())->findForCustomer($this->id('id'), (int) $user['id']);
        if (!$booking) { $this->missing(); return; }
        $this->render('customer/bookings/show', ['title' => 'Booking summary', 'booking' => $booking, 'flight' => (new Flight())->find((int) $booking['flight_id']), 'passengers' => (new Passenger())->forBooking((int) $booking['id']), 'assignments' => (new BookingSeat())->forBooking((int) $booking['id']), 'payments' => (new Payment())->forBooking((int) $booking['id']), 'tickets' => (new Ticket())->forBooking((int) $booking['id']), 'cancellations' => (new Cancellation())->forBooking((int) $booking['id'])]);
    }
    private function id(string $key): int
    {
        $value = $_GET[$key] ?? null;
        $id = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) { $this->missing(); exit; }
        return $id;
    }
    private function input(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : ''; }
    private function form(array $flight, string $token, array $values = [], array $errors = []): void
    {
        $this->render('customer/bookings/form', ['title' => 'Passenger details', 'flight' => $flight, 'booking_token' => $token, 'values' => $values, 'errors' => $errors, 'today' => gmdate('Y-m-d')]);
    }
    private function unavailable(): void { http_response_code(409); $this->render('customer/flights/unavailable', ['title' => 'Flight unavailable']); }
    private function expired(): void { http_response_code(409); $this->render('customer/bookings/error', ['title' => 'Booking form expired', 'message' => 'Open a new booking form from flight details and try again.']); }
    private function missing(): void { http_response_code(404); $this->render('customer/bookings/error', ['title' => 'Booking not found', 'message' => 'The requested booking or flight does not exist or is not accessible.']); }
}
