<?php
declare(strict_types=1);
namespace App\Core;
use Dompdf\Dompdf;
use Dompdf\Options;

final class TicketPdf
{
    public static function render(array $ticket): string
    {
        require_once BASE_PATH . '/vendor/autoload.php';
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        try {
            require BASE_PATH . '/app/Views/tickets/download.php';
            $html = ob_get_contents();
        } finally { ob_end_clean(); }
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', BASE_PATH . '/vendor/dompdf/dompdf/lib/fonts');
        $renderer = new Dompdf($options);
        $renderer->setPaper('A4', 'portrait');
        $renderer->loadHtml($html, 'UTF-8');
        $renderer->render();
        return $renderer->output();
    }
}
