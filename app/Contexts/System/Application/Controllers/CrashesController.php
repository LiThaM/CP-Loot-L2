<?php

namespace App\Contexts\System\Application\Controllers;

use App\Contexts\ClientApi\Domain\Models\CrashReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CrashesController extends Controller
{
    public function index(): Response
    {
        $groups = CrashReport::query()
            ->select(
                'fingerprint',
                DB::raw('COUNT(*) as count'),
                DB::raw('MIN(reported_at) as first_seen'),
                DB::raw('MAX(reported_at) as last_seen'),
                DB::raw('MAX(message) as sample_message'),
                DB::raw('MAX(bot_version) as last_bot_version')
            )
            ->groupBy('fingerprint')
            ->orderByDesc('last_seen')
            ->limit(200)
            ->get();

        return Inertia::render('System/Crashes/Index', [
            'groups' => $groups,
        ]);
    }

    public function show(string $fingerprint): Response
    {
        $reports = CrashReport::where('fingerprint', $fingerprint)
            ->orderByDesc('reported_at')
            ->limit(50)
            ->get(['id', 'bot_version', 'os_version', 'python_version', 'message', 'stack_trace', 'context_json', 'reported_at']);

        return Inertia::render('System/Crashes/Show', [
            'fingerprint' => $fingerprint,
            'reports' => $reports,
        ]);
    }

    public function destroy(Request $request, string $fingerprint): RedirectResponse
    {
        CrashReport::where('fingerprint', $fingerprint)->delete();
        return redirect()->route('system.crashes.index')->with('success', 'Crash group deleted.');
    }
}
