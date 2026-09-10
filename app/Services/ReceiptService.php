<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptService
{
    public function generateReceipt(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing('orderItems', 'payment');

        return Pdf::loadView('pdfs.receipt', ['order' => $order])
            ->setPaper('a4');
    }

    public function generateReservationReceipt(Reservation $reservation): \Barryvdh\DomPDF\PDF
    {
        $reservation->loadMissing('reservationFoods', 'payment', 'table');

        return Pdf::loadView('pdfs.reservation-receipt', ['reservation' => $reservation])
            ->setPaper('a4');
    }
}
