<?php

namespace App\Http\Middleware;

use App\Contexts\Identity\Domain\Models\User;
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
            'translations' => $translations,
        ];
    }
}
