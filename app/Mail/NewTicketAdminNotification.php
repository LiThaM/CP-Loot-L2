<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewTicketAdminNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SupportTicket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        $number = $this->ticket->ticket_number ?: ('#'.$this->ticket->id);
        $envelope = new Envelope(
            subject: "[Ticket {$number}] ".$this->ticket->subject,
        );

        if ($this->ticket->email) {
            $envelope->replyTo = [new Address($this->ticket->email, $this->ticket->name ?: $this->ticket->email)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tickets.admin_new',
            with: [
                'ticket' => $this->ticket,
                'showUrl' => route('tickets.show', ['ticket' => $this->ticket->id]),
            ],
        );
    }
}
