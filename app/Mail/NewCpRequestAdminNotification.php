<?php

namespace App\Mail;

use App\Contexts\Party\Domain\Models\CpRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewCpRequestAdminNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public CpRequest $cpRequest, public ?string $inviteLink = null)
    {
    }

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: 'Nueva solicitud de CP: '.$this->cpRequest->cp_name,
        );

        if ($this->cpRequest->contact_email) {
            $envelope->replyTo = [new Address($this->cpRequest->contact_email, $this->cpRequest->leader_name ?: $this->cpRequest->contact_email)];
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cp_requests.admin_new',
            with: [
                'cpRequest' => $this->cpRequest,
                'inviteLink' => $this->inviteLink,
            ],
        );
    }
}
