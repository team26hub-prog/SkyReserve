<?php $name = $ticket['full_name'] ?? trim($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
<?php require BASE_PATH . '/app/Views/tickets/visual-assets.php'; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= $escape($ticket['ticket_number']) ?> | SkyReserve</title>
<style>
@page { size: A4; margin: 15mm; }
body { margin: 0; color: #032539; font: 10pt/1.5 'DejaVu Sans', sans-serif; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
td { vertical-align: top; word-wrap: break-word; }
.heading { border-bottom: 3pt solid #FA991C; margin-bottom: 14pt; }
.heading td { padding: 0 0 10pt; }
.logo { width: 48pt; height: 48pt; margin-right: 8pt; vertical-align: middle; }
.brand { display: inline-block; vertical-align: middle; font-size: 18pt; font-weight: bold; white-space: nowrap; }.brand span { color: #FA991C; }
h1 { font-size: 13pt; margin: 2pt 0 0; }
.ticket-number { text-align: right; padding-top: 8pt !important; font-size: 9pt; }
.label { color: #032539; font-size: 8pt; font-weight: normal; }
.value { font-weight: bold; margin-top: 3pt; }
.route { background: #fff8ee; border: 1pt solid #dce3e5; margin-bottom: 12pt; }
.route td { padding: 12pt; }.code { font-size: 25pt; font-weight: bold; }
.arrow { text-align: center; color: #032539; font-size: 18pt; vertical-align: middle; }
.arrow img { width: 28pt; height: 28pt; }
.route-time { font-size: 8pt; font-weight: bold; margin-top: 10pt; }
.details { border: 1pt solid #dce3e5; }.details td { padding: 8pt 12pt; border-bottom: 1pt solid #edf1f2; }
.status { display: inline-block; padding: 2pt 8pt; border: 1pt solid #dce3e5; border-radius: 10pt; background: #f0f2f3; font-size: 8pt; }
.invalid { color: #032539; background: #fff8ee; border-left: 3pt solid #FA991C; padding: 8pt 12pt; font-weight: bold; }
.footer { margin-top: 12pt; border-top: 1pt dashed #9bb5c2; font-size: 7pt; }
.footer td { padding: 10pt 0 0; vertical-align: top; }
.footer p { margin: 0 0 5pt; }
.barcode { width: 220pt; height: 36pt; }
.barcode-label { text-align: center; margin-top: 6pt; }
</style></head><body>
<table class="heading"><tr><td><img class="logo" src="<?= $ticketVisuals['logo'] ?>" alt="SkyReserve logo"><div class="brand">Sky<span>Reserve</span></div><h1>Electronic ticket</h1></td><td class="ticket-number"><div class="label">Ticket number</div><div class="value"><?= $escape($ticket['ticket_number']) ?></div></td></tr></table>
<table class="route"><tr><td style="width: 42%"><div class="label">Departure</div><div class="code"><?= $escape($ticket['origin_code']) ?></div><?= $escape($ticket['origin_name']) ?><div class="route-time"><div class="label">Departure (UTC)</div><?= $escape($ticket['departure_at']) ?></div></td><td class="arrow" style="width: 16%"><img src="<?= $ticketVisuals['plane'] ?>" alt=""><div>→</div></td><td style="width: 42%"><div class="label">Arrival</div><div class="code"><?= $escape($ticket['destination_code']) ?></div><?= $escape($ticket['destination_name']) ?><div class="route-time"><div class="label">Arrival (UTC)</div><?= $escape($ticket['arrival_at']) ?></div></td></tr></table>
<table class="details">
<?php foreach ([
    ['Booking reference / PNR', $ticket['booking_reference'], 'Booking status', \App\Models\Booking::statusLabel($ticket['booking_status'])],
    ['Passenger name', $name, 'CNIC / Passport', $ticket['document_number'] ?? ''],
    ['Flight number', $ticket['flight_number'], 'Seat and class', $ticket['seat_number'] . ' / ' . ucfirst($ticket['cabin_class'])],
    ['Fare at booking', $ticket['currency'] . ' ' . $ticket['total_amount'], 'Ticket status', ucfirst($ticket['status'])],
    ['Issued at (UTC)', $ticket['issued_at'], '', ''],
] as [$leftLabel, $leftValue, $rightLabel, $rightValue]): ?>
<tr><td><div class="label"><?= $escape($leftLabel) ?></div><div class="value"><?= $escape($leftValue) ?></div></td><td><div class="label"><?= $escape($rightLabel) ?></div><div class="value"><?php if (in_array($rightLabel, ['Booking status', 'Ticket status'], true)): ?><span class="status"><?= $escape($rightValue) ?></span><?php else: ?><?= $escape($rightValue) ?><?php endif; ?></div></td></tr>
<?php endforeach; ?>
</table>
<?php if ($ticket['status'] === 'void'): ?><p class="invalid">VOID TICKET — This ticket is no longer valid for travel.</p><?php endif; ?>
<table class="footer"><tr>
<?php if ($ticketVisuals['barcode']): ?><td style="width: 48%"><img class="barcode" src="<?= $ticketVisuals['barcode'] ?>" alt="Booking reference barcode"><div class="barcode-label">PNR <?= $escape($ticket['booking_reference']) ?></div></td><?php endif; ?>
<td style="padding-left: 12pt"><p><strong>Before you travel</strong></p><p>Arrive well before departure and follow the airline's check-in deadline. Carry the travel documents required for your route.</p><p>This electronic ticket records your booking; it is not a boarding pass. Present it at check-in.</p><p>Thank you for choosing SkyReserve. All times shown are UTC.</p></td>
</tr></table>
</body></html>
