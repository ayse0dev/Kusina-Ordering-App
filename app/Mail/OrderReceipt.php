<?php

namespace App\Mail;

use App\Models\Order;
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
}
