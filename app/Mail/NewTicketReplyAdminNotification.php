<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewTicketReplyAdminNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SupportTicket $ticket, public TicketReply $reply)
    {
    }

    public function envelope(): Envelope
    {
        $number = $this->ticket->ticket_number ?: ('#'.$this->ticket->id);
        $envelope = new Envelope(
            subject: "[Ticket {$number}] Nueva respuesta - ".$this->ticket->subject,
        );

        $replier = $this->reply->user;
        if ($replier && $replier->email) {
            $envelope->replyTo = [new Address($replier->email, $replier->name ?: $replier->email)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.admin_reply',
            with: [
                'ticket' => $this->ticket,
                'reply' => $this->reply,
                'showUrl' => route('tickets.show', ['ticket' => $this->ticket->id]),
            ],
        );
    }
}
