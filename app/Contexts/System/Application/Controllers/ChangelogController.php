<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\System\Domain\Models\ChangelogEntry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChangelogController extends Controller
{
    public function index(Request $request)
    {
        $entries = ChangelogEntry::query()
            ->whereIn('audience', ['web', 'both'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get([
                'id',
                'type',
                'version',
                'audience',
                'title_es',
                'title_en',
                'body_es',
                'body_en',
                'published_at',
            ]);

        // Visiting this page clears the navbar pulse badge — drop the
        // "unread" marker by stamping the user's last-seen timestamp.
        // Skipped during impersonation so the admin doesn't dismiss
        // the impersonated user's pending notice on their behalf; the
        // real user still sees their modal on next login.
        if (($user = $request->user()) && ! $request->session()->has('impersonated_by')) {
            $user->forceFill(['changelog_last_seen_at' => now()])->save();
        }

        return Inertia::render('Changelog/Index', [
            'entries' => $entries,
        ]);
    }

    /**
     * Acknowledge unseen changelog entries without rendering the full
     * page — used by the first-open modal so the user can dismiss in
     * place. Same side effect as visiting /changelog: stamps
     * `changelog_last_seen_at = now()`.
     */
    public function acknowledge(Request $request)
    {
        // Impersonation never bumps the impersonated user's last-seen
        // timestamp — the real account must keep seeing the modal
        // when they log in themselves.
        if ($request->session()->has('impersonated_by')) {
            return back();
        }

        $user = $request->user();
        if ($user) {
            $user->forceFill(['changelog_last_seen_at' => now()])->save();
        }
        return back();
    }
}
