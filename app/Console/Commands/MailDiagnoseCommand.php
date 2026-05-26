<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Run with `php artisan mail:diagnose [email]` on the server to verify
 * whether the configured transport actually sends out a message. Prints
 * the relevant config (without the secret), attempts a Mail::raw, and
 * reports the exception class + message if anything throws — which is
 * what TicketController quietly swallows in production today.
 */
class MailDiagnoseCommand extends Command
{
    protected $signature = 'mail:diagnose {to? : Destination email (defaults to SUPPORT_MAIL_TO)}';

    protected $description = 'Dump effective mail config and attempt a test send';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $from = config('mail.from.address');
        $fromName = config('mail.from.name');
        $supportTo = config('services.support.mail_to');
        $to = $this->argument('to') ?: $supportTo;

        $mgDomain = config('services.mailgun.domain');
        $mgSecret = config('services.mailgun.secret');
        $mgEndpoint = config('services.mailgun.endpoint');

        $this->line('Mail transport ........ '.($mailer ?: '(none)'));
        $this->line('From .................. '.$fromName.' <'.$from.'>');
        $this->line('SUPPORT_MAIL_TO ....... '.($supportTo ?: '(empty)'));
        $this->line('Mailgun domain ........ '.($mgDomain ?: '(empty)'));
        $this->line('Mailgun secret ........ '.($mgSecret ? 'set ('.substr($mgSecret, 0, 8).'…)' : '(empty)'));
        $this->line('Mailgun endpoint ...... '.($mgEndpoint ?: '(empty)'));
        $this->newLine();

        if (! $to) {
            $this->error('No destination — pass one as argument or set SUPPORT_MAIL_TO.');
            return self::FAILURE;
        }

        $this->info('Attempting to send a test message to '.$to.'…');

        try {
            Mail::raw('mail:diagnose — '.now()->toIso8601String(), function ($m) use ($to) {
                $m->to($to)->subject('[diagnose] CP-Loot-L2 mail test');
            });
            $this->info('Mail::raw returned without throwing.');
            $this->comment('If you do NOT see the message in your inbox in ~1 minute:');
            $this->comment('  - Check Mailgun logs at mailgun.com → Sending → Logs.');
            $this->comment('  - Verify '.$to.' has a real mailbox / forwarding rule.');
            $this->comment('  - Inspect storage/logs/laravel.log for transport-level errors after this run.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Send threw '.get_class($e).': '.$e->getMessage());
            $this->newLine();
            $this->line('Likely causes:');
            $this->line('  - MAILGUN_DOMAIN or MAILGUN_SECRET missing in .env.');
            $this->line('  - Stale config cache: run `php artisan config:clear`.');
            $this->line('  - Mailgun API key expired / domain not verified.');
            return self::FAILURE;
        }
    }
}
