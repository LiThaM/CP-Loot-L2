<?php

namespace App\Mail;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ChangelogEntryPublished extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ChangelogEntry $entry,
        public User $recipient,
    ) {
    }

    public function envelope(): Envelope
    {
        $lang = $this->resolvedLang();
        $title = $lang === 'en' ? $this->entry->title_en : $this->entry->title_es;

        return new Envelope(
            subject: '[' . config('app.name') . '] ' . $title,
        );
    }

    public function content(): Content
    {
        $lang = $this->resolvedLang();
        $title = $lang === 'en' ? $this->entry->title_en : $this->entry->title_es;
        $rawBody = $lang === 'en' ? $this->entry->body_en : $this->entry->body_es;

        return new Content(
            view: 'emails.changelog.published',
            with: [
                'kicker' => $lang === 'en' ? 'New feature' : 'Nueva feature',
                'title' => $title,
                'bodyHtml' => $rawBody ? (string) Str::markdown($rawBody) : '',
                'changelogUrl' => url('/changelog?lang=' . $lang),
                'profileUrl' => url('/profile?lang=' . $lang),
                'unsubscribeText' => $lang === 'en'
                    ? 'You can stop receiving these in your profile preferences.'
                    : 'Puedes dejar de recibir estos avisos en las preferencias de tu perfil.',
                'ctaText' => $lang === 'en' ? 'Read on /changelog' : 'Leer en /changelog',
                'lang' => $lang,
            ],
        );
    }

    private function resolvedLang(): string
    {
        $pref = $this->recipient->language_preference ?? 'system';
        return $pref === 'en' ? 'en' : 'es';
    }
}
