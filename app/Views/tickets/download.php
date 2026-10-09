<?php $name = $ticket['full_name'] ?? trim($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= $escape($ticket['ticket_number']) ?> | SkyReserve</title>
<style>
@page { size: A4; margin: 15mm; }
body { margin: 0; color: #032539; font: 10pt/1.5 'DejaVu Sans', sans-serif; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
td { vertical-align: top; word-wrap: break-word; }
.heading { border-bottom: 3pt solid #FA991C; margin-bottom: 14pt; }
.heading td { padding: 0 0 12pt; }
.brand { font-size: 22pt; font-weight: bold; }.brand span { color: #FA991C; }
h1 { font-size: 16pt; margin: 2pt 0 0; }
.ticket-number { text-align: right; padding-top: 8pt !important; font-size: 9pt; }
.label { color: #31576a; font-size: 8pt; font-weight: normal; }
.value { font-weight: bold; margin-top: 3pt; }
.route { background: #FBF3F2; border: 1pt solid #dce3e5; margin-bottom: 14pt; }
.route td { padding: 12pt; }.code { font-size: 25pt; font-weight: bold; }
.arrow { text-align: center; color: #1C768F; font-size: 20pt; vertical-align: middle; }
.details { border: 1pt solid #dce3e5; }.details td { padding: 10pt 12pt; border-bottom: 1pt solid #edf1f2; }
.invalid { color: #9b2525; background: #fff3f3; padding: 10pt 12pt; font-weight: bold; }
.footer { margin-top: 14pt; padding-top: 10pt; border-top: 1pt dashed #9bb5c2; font-size: 8pt; }
</style></head><body>
<table class="heading"><tr><td><div class="brand">Sky<span>Reserve</span></div><h1>Electronic ticket</h1></td><td class="ticket-number"><div class="label">Ticket number</div><div class="value"><?= $escape($ticket['ticket_number']) ?></div></td></tr></table>
<table class="route"><tr><td style="width: 42%"><div class="label">Departure</div><div class="code"><?= $escape($ticket['origin_code']) ?></div><?= $escape($ticket['origin_name']) ?></td><td class="arrow" style="width: 16%">→</td><td style="width: 42%"><div class="label">Arrival</div><div class="code"><?= $escape($ticket['destination_code']) ?></div><?= $escape($ticket['destination_name']) ?></td></tr></table>
<table class="details">
<?php foreach ([
    ['Booking reference / PNR', $ticket['booking_reference'], 'Booking status', \App\Models\Booking::statusLabel($ticket['booking_status'])],
    ['Passenger name', $name, 'CNIC / Passport', $ticket['document_number'] ?? ''],
    ['Flight number', $ticket['flight_number'], 'Seat and class', $ticket['seat_number'] . ' / ' . ucfirst($ticket['cabin_class'])],
    ['Departure (UTC)', $ticket['departure_at'], 'Arrival (UTC)', $ticket['arrival_at']],
    ['Fare at booking', $ticket['currency'] . ' ' . $ticket['total_amount'], 'Ticket status', ucfirst($ticket['status'])],
    ['Issued at (UTC)', $ticket['issued_at'], '', ''],
] as [$leftLabel, $leftValue, $rightLabel, $rightValue]): ?>
<tr><td><div class="label"><?= $escape($leftLabel) ?></div><div class="value"><?= $escape($leftValue) ?></div></td><td><div class="label"><?= $escape($rightLabel) ?></div><div class="value"><?= $escape($rightValue) ?></div></td></tr>
<?php endforeach; ?>
</table>
<?php if ($ticket['status'] === 'void'): ?><p class="invalid">VOID TICKET — This ticket is no longer valid for travel.</p><?php endif; ?>
<p class="footer">Thank you for choosing SkyReserve. All times shown are UTC.</p>
</body></html>
