<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderTrackingCodeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.app(\App\Support\ShopBrand::class)->name().' order tracking code');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-tracking-code', with: [
            'shopBrandName' => app(\App\Support\ShopBrand::class)->name(),
        ]);
    }
}
