<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\ReceiptPdf;
use Illuminate\Mail\Mailables\Attachment;
use Throwable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderReceipt extends Mailable
{
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your order '.$this->order->code.' at '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-receipt', with: ['o' => $this->order->loadMissing('items.options')]);
    }

    public function attachments(): array
    {
        try {
            $pdf = ReceiptPdf::order($this->order);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return [Attachment::fromData(fn () => $pdf, 'Receipt-'.$this->order->code.'.pdf')->withMime('application/pdf')];
    }
}
