<?php

namespace App\Mail;

use App\Models\NotificationDispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public NotificationDispatch $dispatch) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->dispatch->displaySubject());
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-status-notification',
            with: [
                'dispatch' => $this->dispatch,
                'subject' => $this->dispatch->displaySubject(),
                'snapshot' => $this->dispatch->displaySnapshot(),
            ],
        );
    }
}
