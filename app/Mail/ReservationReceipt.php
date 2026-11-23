<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Services\ReceiptPdf;
use Illuminate\Mail\Mailables\Attachment;
use Throwable;
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

    public function attachments(): array
    {
        try {
            $pdf = ReceiptPdf::reservation($this->reservation);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return [Attachment::fromData(fn () => $pdf, 'Receipt-'.$this->reservation->code.'.pdf')->withMime('application/pdf')];
    }
}
