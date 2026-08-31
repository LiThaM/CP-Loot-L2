<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReplyAuthorNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SupportTicket $ticket, public TicketReply $reply) {}

    public function envelope(): Envelope
    {
        $number = $this->ticket->ticket_number ?: ('#'.$this->ticket->id);

        return new Envelope(
            subject: "Nueva respuesta en tu ticket {$number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.author_reply',
            with: [
                'ticket' => $this->ticket,
                'reply' => $this->reply,
                'showUrl' => $this->ticket->user_id
                    ? route('tickets.show', ['ticket' => $this->ticket->id])
                    : null,
            ],
        );
    }
}
