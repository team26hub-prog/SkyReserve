<?php
// Presentation-only assets shared by the screen/print ticket and the PDF template.
$ticketVisuals = (static function (array $ticket): array {
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(BASE_PATH . '/public/assets/images/skyreserve-ticket-logo.png'));
    $plane = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#FA991C" d="M21 3a2.1 2.1 0 0 1 0 3l-5 5 2 8-2 2-4-7-5 5v3l-2-2-2-2h3l5-5-7-4 2-2 8 2 5-5Z"/></svg>';
    $barcode = null;
    $reference = (string) $ticket['booking_reference'];
    // Standard Code 39 narrow/wide patterns. No check digit or ticket validation is added.
    // Pattern reference: https://github.com/zxing/zxing/blob/master/core/src/main/java/com/google/zxing/oned/Code39Reader.java
    if (preg_match('/\A[0-9A-Z.-]{1,40}\z/', $reference)) {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-.';
        $patterns = [0x034,0x121,0x061,0x160,0x031,0x130,0x070,0x025,0x124,0x064,
            0x109,0x049,0x148,0x019,0x118,0x058,0x00D,0x10C,0x04C,0x01C,
            0x103,0x043,0x142,0x013,0x112,0x052,0x007,0x106,0x046,0x016,
            0x181,0x0C1,0x1C0,0x091,0x190,0x0D0,0x085,0x184];
        $bars = ''; $x = 10;
        foreach (str_split('*' . $reference . '*') as $character) {
            $pattern = $character === '*' ? 0x094 : $patterns[strpos($alphabet, $character)];
            for ($part = 0; $part < 9; ++$part) {
                $width = ($pattern & (1 << (8 - $part))) ? 3 : 1;
                if ($part % 2 === 0) $bars .= '<rect x="' . $x . '" y="0" width="' . $width . '" height="36"/>';
                $x += $width;
            }
            ++$x;
        }
        $barcode = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . ($x + 9) . ' 36"><rect width="100%" height="100%" fill="#fff"/><g fill="#032539">' . $bars . '</g></svg>');
    }
    return ['logo' => $logo, 'plane' => 'data:image/svg+xml;base64,' . base64_encode($plane), 'barcode' => $barcode];
})($ticket);
