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
        return new Envelope(
            subject: '[' . config('app.name') . '] ' . $this->localized('title'),
        );
    }

    public function content(): Content
    {
        $lang = $this->resolvedLang();
        $rawBody = $this->localized('body');

        $kicker = ['es' => 'Nueva feature', 'en' => 'New feature', 'it' => 'Nuova funzione', 'ru' => 'Новая функция'];
        $unsub = [
            'es' => 'Puedes dejar de recibir estos avisos en las preferencias de tu perfil.',
            'en' => 'You can stop receiving these in your profile preferences.',
            'it' => 'Puoi smettere di riceverli nelle preferenze del tuo profilo.',
            'ru' => 'Отключить эти уведомления можно в настройках профиля.',
        ];
        $cta = ['es' => 'Leer en /changelog', 'en' => 'Read on /changelog', 'it' => 'Leggi su /changelog', 'ru' => 'Открыть /changelog'];

        return new Content(
            view: 'emails.changelog.published',
            with: [
                'kicker' => $kicker[$lang] ?? $kicker['en'],
                'title' => $this->localized('title'),
                'bodyHtml' => $rawBody ? (string) Str::markdown($rawBody) : '',
                'changelogUrl' => url('/changelog?lang=' . $lang),
                'profileUrl' => url('/profile?lang=' . $lang),
                'unsubscribeText' => $unsub[$lang] ?? $unsub['en'],
                'ctaText' => $cta[$lang] ?? $cta['en'],
                'lang' => $lang,
            ],
        );
    }

    /** Pick `{field}_{lang}` with fallback to English then Spanish. */
    private function localized(string $field): string
    {
        $lang = $this->resolvedLang();

        return (string) ($this->entry->{"{$field}_{$lang}"}
            ?: ($this->entry->{"{$field}_en"} ?: ($this->entry->{"{$field}_es"} ?? '')));
    }

    private function resolvedLang(): string
    {
        $pref = $this->recipient->language_preference ?? 'system';
        return in_array($pref, ['en', 'es', 'it', 'ru'], true) ? $pref : 'es';
    }
}
