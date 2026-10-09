<?php
declare(strict_types=1);
namespace App\Models;
final class Passenger extends Model
{
    public function create(int $bookingId, array $values): void
    {
        $name = $values['full_name'];
        $split = strrpos($name, ' ');
        $first = $split === false ? $name : substr($name, 0, $split);
        $last = $split === false ? '' : substr($name, $split + 1);
        if (mb_strlen($first) > 80 || mb_strlen($last) > 80) { $first = mb_substr($name, 0, 80); $last = mb_substr($name, 80); }
        $this->db()->prepare('INSERT INTO passengers (booking_id, first_name, last_name, full_name, document_number, cnic, passport_number, date_of_birth, gender, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $bookingId, $first, $last, $name, $values['document_number'], $values['cnic'] ?? null, ($values['passport_number'] ?? '') === '' ? null : $values['passport_number'], $values['date_of_birth'], $values['gender'], $values['phone'],
        ]);
    }
    public function forBooking(int $id): array
    {
        $query = $this->db()->prepare('SELECT * FROM passengers WHERE booking_id = ? ORDER BY id');
        $query->execute([$id]);
        return $query->fetchAll();
    }
}
