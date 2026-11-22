<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReservationReceipt extends Mailable
{
    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your reservation '.$this->reservation->code.' at '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reservation-receipt', with: ['r' => $this->reservation->loadMissing('table.floor')]);
    }
}
