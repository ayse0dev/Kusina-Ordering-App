<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReceiptPdf
{
    public static function reservation(Reservation $reservation): string
    {
        return self::render('pdf.reservation', ['r' => $reservation->loadMissing('table.floor')]);
    }

    public static function order(Order $order): string
    {
        return self::render('pdf.order', ['o' => $order->loadMissing('items.options')]);
    }

    private static function render(string $view, array $data): string
    {
        $cache = storage_path('app/dompdf');
        if (! is_dir($cache)) {
            mkdir($cache, 0775, true);
        }

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', $cache);
        $options->set('fontCache', $cache);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->setPaper('a5', 'portrait');
        $pdf->render();

        return $pdf->output();
    }
}
