<?php
use App\Models\Booking;
use App\Models\Payment;
$columns = match ($type) {
    'bookings' => ['booking_reference' => ['PNR','text'],'customer' => ['Customer','text'],'route' => ['Flight / route','route'],'created_at' => ['Booked (UTC)','text'],'departure_at' => ['Departure (UTC)','text'],'passengers' => ['Passengers','text'],'seats' => ['Active seats','text'],'tickets' => ['Valid tickets','text'],'total_amount' => ['Booked fare','money'],'booking_status' => ['Booking status','status'],'payment_status' => ['Latest payment','status']],
    'flights' => ['flight_number' => ['Flight','text'],'route' => ['Route','route'],'aircraft' => ['Aircraft','text'],'registration_number' => ['Registration','text'],'departure_at' => ['Departure (UTC)','text'],'arrival_at' => ['Arrival (UTC)','text'],'base_fare' => ['Base fare','money'],'configured' => ['Active seats','text'],'occupied' => ['Occupied seats','text'],'unassigned' => ['Unassigned seats','text'],'bookings' => ['Total bookings','text'],'flight_status' => ['Status','status']],
    'payments' => ['booking_reference' => ['PNR','text'],'customer' => ['Customer','text'],'route' => ['Flight / route','route'],'amount' => ['Submitted amount','money'],'method' => ['Method','method'],'transaction_reference' => ['Transaction reference','text'],'payment_date' => ['Payment date (UTC)','text'],'created_at' => ['Submitted (UTC)','text'],'payment_status' => ['Payment status','status'],'booking_status' => ['Booking status','status'],'reviewed_at' => ['Reviewed (UTC)','text']],
    'cancellations' => ['booking_reference' => ['PNR','text'],'customer' => ['Customer','text'],'route' => ['Flight / route','route'],'created_at' => ['Requested (UTC)','text'],'reason' => ['Reason','text'],'cancellation_status' => ['Request status','status'],'booking_status' => ['Booking status','status'],'previous_booking_status' => ['Previous booking status','status'],'reviewer' => ['Reviewer','text'],'reviewed_at' => ['Reviewed (UTC)','text'],'review_notes' => ['Admin note','text']],
    'passengers' => ['passenger' => ['Passenger','text'],'document_number' => ['CNIC / Passport','text'],'phone' => ['Phone','text'],'booking_reference' => ['PNR','text'],'route' => ['Flight / route','route'],'departure_at' => ['Departure (UTC)','text'],'seat_number' => ['Seat','text'],'cabin_class' => ['Class','class'],'seat_status' => ['Seat status','status'],'passenger_status' => ['Passenger status','status'],'booking_status' => ['Booking status','status'],'ticket_number' => ['Ticket number','ticket'],'ticket_status' => ['Ticket status','status']],
};
?>
<div class="table-scroll" tabindex="0" role="region" aria-label="<?= $escape($data['title']) ?> results"><table class="report-table"><caption class="sr-only"><?= $escape($data['title']) ?>, filtered and paginated results</caption><thead><tr><?php foreach ($columns as [$label,$format]): ?><th scope="col"><?= $escape($label) ?></th><?php endforeach; ?><?php if (in_array($type,['flights','payments','cancellations'],true)): ?><th scope="col">Details</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($result['rows'] as $row): ?><tr data-report-row="<?= (int) $row['id'] ?>">
    <?php foreach ($columns as $key => [$label,$format]): $value = $row[$key] ?? null; ?><td>
        <?php if ($format === 'route'): ?><?= $type !== 'flights' ? $escape($row['flight_number']) : '' ?><small class="cell-note"><?= $escape($row['origin_code'] . ' → ' . $row['destination_code']) ?></small>
        <?php elseif ($format === 'status' && $value !== null): ?><span class="badge" data-status="<?= $escape($value) ?>"><?= $escape(in_array($key,['booking_status','previous_booking_status'],true) ? Booking::statusLabel($value) : ucfirst($value)) ?></span>
        <?php elseif ($format === 'money'): ?><?= $escape($row['currency'] . ' ' . $value) ?>
        <?php elseif ($format === 'method'): ?><?= $escape(Payment::METHODS[$value] ?? 'Not provided') ?>
        <?php elseif ($format === 'class'): ?><?= $value ? $escape(ucfirst($value)) : 'Not assigned' ?>
        <?php elseif ($format === 'ticket' && $value): ?><a href="/admin/tickets/show?id=<?= (int) $row['ticket_id'] ?>"><?= $escape($value) ?></a>
        <?php else: ?><?= $value === null ? ($key === 'payment_status' ? 'Not submitted' : 'Not provided') : $escape((string) $value) ?><?php endif; ?>
    </td><?php endforeach; ?>
    <?php if (in_array($type,['flights','payments','cancellations'],true)): ?><td><a class="button button-secondary" href="/admin/<?= $type ?>/show?id=<?= (int) $row['id'] ?>">View details</a></td><?php endif; ?>
</tr><?php endforeach; ?>
</tbody></table></div>
