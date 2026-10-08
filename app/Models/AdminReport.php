<?php
declare(strict_types=1);
namespace App\Models;
use DateTimeImmutable;
use DomainException;
use PDO;

final class AdminReport extends Model
{
    public const PAGE_SIZE = 50;
    public const PAYMENT_STATUSES = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'refunded' => 'Refunded'];
    public const REPORTS = [
        'bookings' => ['title' => 'Bookings Report', 'date' => 'Booking creation', 'statuses' => ['booking_status','payment_status']],
        'flights' => ['title' => 'Flights Report', 'date' => 'Flight departure', 'statuses' => ['flight_status']],
        'payments' => ['title' => 'Payments Report', 'date' => 'Payment submission', 'statuses' => ['payment_status','booking_status']],
        'cancellations' => ['title' => 'Cancellations Report', 'date' => 'Cancellation request', 'statuses' => ['cancellation_status','booking_status']],
        'passengers' => ['title' => 'Passenger List by Flight', 'date' => 'Flight departure', 'statuses' => ['booking_status']],
    ];
    public function dashboard(): array
    {
        $metrics = $this->db()->query("SELECT
            (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customers,
            (SELECT COUNT(*) FROM flights) AS flights,
            (SELECT COUNT(*) FROM flights WHERE status IN ('scheduled','delayed') AND departure_at > UTC_TIMESTAMP()) AS upcoming_flights,
            (SELECT COUNT(*) FROM bookings) AS bookings,
            (SELECT COUNT(*) FROM bookings WHERE status = 'confirmed') AS confirmed_bookings,
            (SELECT COUNT(*) FROM payments WHERE status = 'pending') AS pending_payments,
            (SELECT COUNT(*) FROM cancellations WHERE status = 'pending') AS pending_cancellations")->fetch();
        $metrics['revenue'] = $this->db()->query("SELECT currency, SUM(amount) AS amount FROM payments WHERE status = 'verified' GROUP BY currency ORDER BY currency")->fetchAll();
        return $metrics;
    }
    public function flightOptions(): array
    {
        return $this->db()->query('SELECT id, flight_number, departure_at FROM flights ORDER BY departure_at DESC, id DESC')->fetchAll();
    }
    public static function statusOptions(string $field, string $type): array
    {
        return match ($field) {
            'booking_status' => Booking::STATUSES,
            'payment_status' => self::PAYMENT_STATUSES + ($type === 'bookings' ? ['not_submitted' => 'Not submitted'] : []),
            'flight_status' => Flight::STATUSES,
            'cancellation_status' => Cancellation::STATUSES,
            default => [],
        };
    }
    public function filters(string $type, array $input): array
    {
        if (!isset(self::REPORTS[$type])) throw new DomainException('Unknown report.');
        $allowed = ['start_date','end_date','flight_id','page',...self::REPORTS[$type]['statuses']];
        foreach ($input as $key => $value) if (!in_array($key,$allowed,true) || !is_string($value)) throw new DomainException('Use valid report filter fields.');
        $filters = ['start_date' => '', 'end_date' => '', 'flight_id' => '', 'page' => 1];
        foreach (self::REPORTS[$type]['statuses'] as $field) $filters[$field] = '';
        foreach (['start_date','end_date'] as $field) {
            $value = $input[$field] ?? '';
            if ($value !== '') {
                $date = preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $value) ? DateTimeImmutable::createFromFormat('!Y-m-d',$value) : false;
                if (!$date || $date->format('Y-m-d') !== $value || $value < '1000-01-01') throw new DomainException('Enter valid dates in YYYY-MM-DD format.');
            }
            $filters[$field] = $value;
        }
        if ($filters['start_date'] !== '' && $filters['end_date'] !== '' && $filters['start_date'] > $filters['end_date']) throw new DomainException('Start date must be on or before end date.');
        if (($input['flight_id'] ?? '') !== '') {
            $id = preg_match('/\A[1-9][0-9]*\z/', $input['flight_id']) ? filter_var($input['flight_id'],FILTER_VALIDATE_INT,['options' => ['min_range' => 1]]) : false;
            if ($id === false) throw new DomainException('Choose a valid flight.');
            $query = $this->db()->prepare('SELECT id FROM flights WHERE id = ?'); $query->execute([$id]);
            if ($query->fetchColumn() === false) throw new DomainException('The selected flight does not exist.');
            $filters['flight_id'] = (string) $id;
        }
        foreach (self::REPORTS[$type]['statuses'] as $field) {
            $value = $input[$field] ?? '';
            if ($value !== '' && !isset(self::statusOptions($field,$type)[$value])) throw new DomainException('Choose a valid status for this report.');
            $filters[$field] = $value;
        }
        if (isset($input['page'])) {
            $page = preg_match('/\A[1-9][0-9]*\z/', $input['page']) ? filter_var($input['page'],FILTER_VALIDATE_INT,['options' => ['min_range' => 1,'max_range' => 1000000]]) : false;
            if ($page === false) throw new DomainException('Choose a valid report page.'); $filters['page'] = $page;
        }
        return $filters;
    }
    private function specification(string $type): array
    {
        $route = " JOIN airports o ON o.id = f.origin_airport_id JOIN airports d ON d.id = f.destination_airport_id";
        $latestPayment = ' LEFT JOIN payments latest ON latest.id = (SELECT MAX(lp.id) FROM payments lp WHERE lp.booking_id = b.id)';
        $booking = ' JOIN bookings b ON b.id = p.booking_id JOIN users u ON u.id = b.user_id JOIN flights f ON f.id = b.flight_id' . $route;
        return match ($type) {
            'bookings' => [
                'from' => 'bookings b JOIN users u ON u.id = b.user_id JOIN flights f ON f.id = b.flight_id' . $route . $latestPayment,
                'date' => 'b.created_at', 'flight' => 'b.flight_id', 'status' => ['booking_status' => 'b.status','payment_status' => 'latest.status'], 'money' => 'b.total_amount', 'currency' => 'b.currency',
                'columns' => 'b.id, b.booking_reference, u.name AS customer, f.flight_number, o.iata_code AS origin_code, d.iata_code AS destination_code, f.departure_at, b.created_at, b.total_amount, b.currency, b.status AS booking_status, latest.status AS payment_status,
                    COALESCE(pc.passengers,0) AS passengers, COALESCE(sc.seats,0) AS seats, COALESCE(tc.tickets,0) AS tickets',
                'extra' => " LEFT JOIN (SELECT booking_id,COUNT(*) AS passengers FROM passengers GROUP BY booking_id) pc ON pc.booking_id = b.id
                    LEFT JOIN (SELECT booking_id,COUNT(*) AS seats FROM booking_seats WHERE status IN ('reserved','confirmed') GROUP BY booking_id) sc ON sc.booking_id = b.id
                    LEFT JOIN (SELECT bs.booking_id,COUNT(*) AS tickets FROM tickets t JOIN booking_seats bs ON bs.id = t.booking_seat_id WHERE t.status = 'valid' GROUP BY bs.booking_id) tc ON tc.booking_id = b.id",
                'order' => 'b.created_at DESC, b.id DESC',
            ],
            'flights' => [
                'from' => 'flights f JOIN aircraft a ON a.id = f.aircraft_id' . $route,
                'date' => 'f.departure_at', 'flight' => 'f.id', 'status' => ['flight_status' => 'f.status'],
                'columns' => 'f.id, f.flight_number, o.iata_code AS origin_code, d.iata_code AS destination_code, a.model AS aircraft, a.registration_number, f.departure_at, f.arrival_at, f.base_fare, f.currency, f.status AS flight_status, COALESCE(bc.bookings,0) AS bookings,
                    COALESCE(sc.configured,0) AS configured, COALESCE(oc.occupied,0) AS occupied, GREATEST(0,COALESCE(sc.configured,0)-COALESCE(oc.occupied,0)) AS unassigned',
                'extra' => " LEFT JOIN (SELECT flight_id,COUNT(*) AS bookings FROM bookings GROUP BY flight_id) bc ON bc.flight_id = f.id
                    LEFT JOIN (SELECT aircraft_id,COUNT(*) AS configured FROM seats WHERE status = 'active' GROUP BY aircraft_id) sc ON sc.aircraft_id = f.aircraft_id
                    LEFT JOIN (SELECT bs.flight_id,COUNT(*) AS occupied FROM booking_seats bs JOIN seats s ON s.id = bs.seat_id WHERE bs.status IN ('reserved','confirmed') AND s.status = 'active' GROUP BY bs.flight_id) oc ON oc.flight_id = f.id",
                'order' => 'f.departure_at DESC, f.id DESC',
            ],
            'payments' => [
                'from' => 'payments p' . $booking, 'date' => 'p.created_at', 'flight' => 'b.flight_id', 'status' => ['payment_status' => 'p.status','booking_status' => 'b.status'], 'money' => 'p.amount', 'currency' => 'p.currency',
                'columns' => 'p.id, b.booking_reference, u.name AS customer, f.flight_number, o.iata_code AS origin_code, d.iata_code AS destination_code, p.amount, p.currency, p.method, p.transaction_reference, p.payment_date, p.created_at, p.status AS payment_status, b.status AS booking_status, p.reviewed_at',
                'extra' => '', 'order' => 'p.created_at DESC, p.id DESC',
            ],
            'cancellations' => [
                'from' => 'cancellations c JOIN bookings b ON b.id = c.booking_id JOIN users u ON u.id = b.user_id JOIN flights f ON f.id = b.flight_id' . $route . ' LEFT JOIN users reviewer ON reviewer.id = c.reviewed_by',
                'date' => 'c.created_at', 'flight' => 'b.flight_id', 'status' => ['cancellation_status' => 'c.status','booking_status' => 'b.status'],
                'columns' => 'c.id, b.booking_reference, u.name AS customer, f.flight_number, o.iata_code AS origin_code, d.iata_code AS destination_code, c.created_at, c.reason, c.status AS cancellation_status, b.status AS booking_status, c.previous_booking_status, reviewer.name AS reviewer, c.reviewed_at, c.review_notes',
                'extra' => '', 'order' => 'c.created_at DESC, c.id DESC',
            ],
            'passengers' => [
                'from' => 'passengers p' . $booking . " LEFT JOIN booking_seats bs ON bs.booking_id = b.id AND bs.passenger_id = p.id LEFT JOIN seats s ON s.id = bs.seat_id LEFT JOIN tickets t ON t.booking_seat_id = bs.id",
                'date' => 'f.departure_at', 'flight' => 'b.flight_id', 'status' => ['booking_status' => 'b.status'],
                'columns' => "p.id, COALESCE(p.full_name,CONCAT(p.first_name,' ',p.last_name)) AS passenger, p.document_number, p.phone, p.status AS passenger_status, b.booking_reference, b.status AS booking_status, f.flight_number, o.iata_code AS origin_code, d.iata_code AS destination_code, f.departure_at, s.seat_number, s.cabin_class, bs.status AS seat_status, t.id AS ticket_id, t.ticket_number, t.status AS ticket_status",
                'extra' => '', 'order' => 'b.id DESC, p.id',
            ],
            default => throw new DomainException('Unknown report.'),
        };
    }
    public function report(string $type, array $input): array
    {
        // Validation also runs here so a caller cannot inject identifiers or bypass filter rules.
        $filters = $this->filters($type,$input);
        if ($type === 'passengers' && $filters['flight_id'] === '') return ['rows' => [],'total' => 0,'totals' => [],'page' => 1,'pages' => 1,'filters' => $filters];
        $spec = $this->specification($type); $conditions = []; $params = [];
        if ($filters['start_date'] !== '') { $conditions[] = $spec['date'] . ' >= ?'; $params[] = $filters['start_date'] . ' 00:00:00'; }
        if ($filters['end_date'] !== '') { $conditions[] = $spec['date'] . ' <= ?'; $params[] = $filters['end_date'] . ' 23:59:59'; }
        if ($filters['flight_id'] !== '') { $conditions[] = $spec['flight'] . ' = ?'; $params[] = $filters['flight_id']; }
        foreach ($spec['status'] as $field => $column) if ($filters[$field] !== '') {
            if ($filters[$field] === 'not_submitted') $conditions[] = 'latest.id IS NULL';
            else { $conditions[] = $column . ' = ?'; $params[] = $filters[$field]; }
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ',$conditions) : '';
        $query = $this->db()->prepare('SELECT COUNT(*) FROM ' . $spec['from'] . $where); $query->execute($params); $total = (int) $query->fetchColumn();
        $pages = max(1,(int) ceil($total / self::PAGE_SIZE)); $page = min($filters['page'],$pages);
        $query = $this->db()->prepare('SELECT ' . $spec['columns'] . ' FROM ' . $spec['from'] . $spec['extra'] . $where . ' ORDER BY ' . $spec['order'] . ' LIMIT ? OFFSET ?');
        $values = [...$params,self::PAGE_SIZE,($page-1)*self::PAGE_SIZE];
        foreach ($values as $index => $value) $query->bindValue($index+1,$value,is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $query->execute(); $rows = $query->fetchAll(); $totals = [];
        if (isset($spec['money'])) {
            $money = $spec['money']; $currency = $spec['currency'];
            $verified = $type === 'payments' ? ", SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END) AS verified_amount" : '';
            $query = $this->db()->prepare("SELECT $currency AS currency, SUM($money) AS amount$verified FROM " . $spec['from'] . $where . " GROUP BY $currency ORDER BY $currency"); $query->execute($params); $totals = $query->fetchAll();
        }
        return ['rows' => $rows,'total' => $total,'totals' => $totals,'page' => $page,'pages' => $pages,'filters' => $filters];
    }
}
