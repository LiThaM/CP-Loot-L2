<?php

namespace App\Http\Middleware;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\CpRule;
use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Contexts\System\Domain\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $requestedLocale = $request->session()->get('locale') ?: $request->cookie('locale');
        if (is_string($requestedLocale) && in_array($requestedLocale, ['en', 'es'], true)) {
            app()->setLocale($requestedLocale);
        }

        // Obtener traducciones cacheadas o de bd
        try {
            $locale = (string) app()->getLocale();
            $fallback = (string) config('app.fallback_locale', 'en');

            $translations = Translation::where('language', $locale)->pluck('value', 'key')->toArray();
            if (empty($translations) && $fallback && $fallback !== $locale) {
                $translations = Translation::where('language', $fallback)->pluck('value', 'key')->toArray();
            }
            if (empty($translations) && $fallback !== 'es' && $locale !== 'es') {
                $translations = Translation::where('language', 'es')->pluck('value', 'key')->toArray();
            }
            if (empty($translations)) {
                $translations = Translation::pluck('value', 'key')->toArray();
            }
        } catch (\Throwable $e) {
            $translations = [];
        }
        $authUser = $request->user();
        $isBanned = $authUser && (($authUser->membership_status ?? 'approved') === 'banned');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authUser ? clone $authUser->load('role', 'cp') : null,
                'isImpersonating' => $request->session()->has('impersonated_by'),
            ],
            'app' => [
                'name' => (string) config('app.name', 'AdenaLedger'),
                'locale' => (string) app()->getLocale(),
                'supportEmail' => (string) env('SUPPORT_EMAIL', 'support@adenaledger.com'),
                'donationWallet' => (string) env('DONATION_WALLET', '0x0D5cf74c1487a0B3867930E884daa44f5019a40E'),
            ],
            'cpMembers' => fn () => $authUser && $authUser->cp_id
                ? ($isBanned ? [] : User::where('cp_id', $authUser->cp_id)->where('membership_status', '!=', 'banned')->orderBy('name')->get(['id', 'name']))
                : [],
            'alerts' => fn () => $authUser ? [
                'unreadCount' => $isBanned ? 0 : (int) DB::table('audit_alerts')
                    ->where('recipient_user_id', $authUser->id)
                    ->whereNull('read_at')
                    ->count(),
                'items' => $isBanned ? [] : DB::table('audit_alerts')
                    ->select(['id', 'summary', 'entity_type', 'entity_id', 'action', 'read_at', 'created_at'])
                    ->where('recipient_user_id', $authUser->id)
                    ->orderByDesc('created_at')
                    ->limit(6)
                    ->get(),
            ] : [
                'unreadCount' => 0,
                'items' => [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'changelog' => fn () => $this->changelogSummary($authUser, $isBanned),
            'cpRules' => fn () => $this->cpRulesSummary($authUser, $isBanned),
            'translations' => $translations,
        ];
    }

    /**
     * Unread-count for the changelog navbar badge: entries with audience
     * `web`/`both` published after the user's last visit to /changelog.
     */
    private function changelogSummary(?User $user, bool $isBanned): array
    {
        if (!$user || $isBanned) {
            return ['unreadCount' => 0, 'items' => []];
        }

        try {
            $since = $user->changelog_last_seen_at;
            $base = ChangelogEntry::query()
                ->whereIn('audience', ['web', 'both'])
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->when($since, fn ($q) => $q->where('published_at', '>', $since));

            $count = (clone $base)->count();
            // Hydrate up to 10 entries for the first-time-open modal so
            // the layout can render them inline without a follow-up
            // round trip.
            $items = (clone $base)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'type', 'title_es', 'title_en', 'body_es', 'body_en', 'published_at'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'title_es' => $e->title_es,
                    'title_en' => $e->title_en,
                    'body_es' => $e->body_es,
                    'body_en' => $e->body_en,
                    'published_at' => $e->published_at?->toIso8601String(),
                ])
                ->all();
        } catch (\Throwable $e) {
            $count = 0;
            $items = [];
        }

        return ['unreadCount' => (int) $count, 'items' => $items];
    }

    /**
     * CP rules acceptance state. Drives the blocking modal in MainLayout —
     * `mustAccept` is true whenever the user's CP has a rule document
     * whose version is ahead of the user's `cp_rules_accepted_version`.
     */
    private function cpRulesSummary(?User $user, bool $isBanned): array
    {
        $empty = ['hasRules' => false, 'mustAccept' => false, 'current' => null];
        if (!$user || $isBanned || !$user->cp_id) {
            return $empty;
        }

        try {
            $rule = CpRule::with('updatedBy:id,name')->where('cp_id', $user->cp_id)->first();
            if (!$rule) {
                return $empty;
            }

            $accepted = (int) ($user->cp_rules_accepted_version ?? 0);
            $version = (int) $rule->version;

            return [
                'hasRules' => true,
                'mustAccept' => $accepted < $version,
                'acceptedVersion' => $accepted,
                'current' => [
                    'version' => $version,
                    'body' => $rule->body,
                    'updated_at' => $rule->updated_at?->toIso8601String(),
                    'updated_by' => $rule->updatedBy?->name,
                ],
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }
}
