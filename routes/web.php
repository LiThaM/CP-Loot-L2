<?php

use App\Contexts\ClientApi\Domain\Models\Release;
use App\Contexts\Loot\Application\Controllers\PublicCraftingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $latest = Release::published()
        ->channel('stable')
        ->orderByDesc('released_at')
        ->first();

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'botRelease' => $latest ? [
            'version' => $latest->version,
            'size_bytes' => $latest->size_bytes,
            'released_at' => $latest->released_at?->toIso8601String(),
            'critical_update' => (bool) $latest->critical_update,
            'download_url' => $latest->storage_path
                ? url('/api/v1/releases/'.$latest->version.'/download')
                : null,
        ] : null,
    ]);
});

// Landing pública para descarga del bot (solo Lu4).
Route::get('/download', [\App\Http\Controllers\LandingController::class, 'show'])
    ->name('landing.download');

// Tracking público de tickets desde la app (redirect a página Inertia).
Route::get('/t/{token}', function (string $token) {
    return Inertia::render('Tickets/Track', ['trackingToken' => $token]);
})->where('token', '[A-Za-z0-9]{40}')->name('tickets.track');

// Public Recipe Explorer (no auth required)
Route::get('/recipes', function () {
    return Inertia::render('Recipes');
})->name('recipes');

Route::get('/terms', function () {
    return Inertia::render('Legal/Terms');
})->name('legal.terms');

Route::get('/privacy', function () {
    return Inertia::render('Legal/Privacy');
})->name('legal.privacy');

Route::prefix('api/public')->middleware('throttle:30,1')->group(function () {
    Route::get('/recipes/search', [PublicCraftingController::class, 'search'])->name('public.recipes.search');
    Route::get('/recipes/{recipe}/tree', [PublicCraftingController::class, 'tree'])->name('public.recipes.tree');
    Route::get('/recipes/chronicles', [PublicCraftingController::class, 'chronicles'])->name('public.recipes.chronicles');
});

Route::post('/locale', function (\Illuminate\Http\Request $request) {
    $data = $request->validate([
        'locale' => 'required|string|in:en,es',
    ]);

    $request->session()->put('locale', $data['locale']);

    return back();
})->name('locale.set');

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Admin\ImpersonateController;

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/admin/impersonate/{user}', [ImpersonateController::class, 'take'])->name('admin.impersonate');
    Route::post('/admin/stop-impersonating', [ImpersonateController::class, 'leave'])->name('admin.impersonate.leave');
});

Route::post('/support/contact', [SupportController::class, 'contact'])
    ->middleware('throttle:10,1')
    ->name('support.contact');

Route::post('/cp-requests', [SupportController::class, 'cpRequest'])
    ->middleware('throttle:6,1')
    ->name('cp.requests.store');

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Application\Controllers\AdenaActionController;
use App\Contexts\Loot\Application\Controllers\CpEventConfigController;
use App\Contexts\Loot\Application\Controllers\CraftingController;
use App\Contexts\Loot\Application\Controllers\LootActionController;
use App\Contexts\Loot\Application\Controllers\LootController;
use App\Contexts\Loot\Application\Controllers\LootSearchController;
use App\Contexts\Loot\Application\Controllers\WishlistController;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Application\Controllers\ConstPartyController;
use App\Contexts\Party\Application\Controllers\PartyController;
use App\Contexts\Party\Domain\Models\ConstParty;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\System\Application\Controllers\ChangelogController;
use App\Contexts\System\Application\Controllers\ItemManagementController;
use App\Contexts\System\Application\Controllers\TranslationController;
use App\Contexts\System\Application\Controllers\UserManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // L2 characters — own page, separate from the web-account profile.
    Route::get('/characters', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'index'])
        ->name('characters.index');
    Route::post('/characters', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'store'])
        ->name('characters.store');
    Route::patch('/characters/{character}', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'update'])
        ->name('characters.update');
    Route::delete('/characters/{character}', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'destroy'])
        ->name('characters.destroy');
    // Legacy aliases kept so old tests / cached frontend routes keep
    // resolving until they get rebuilt.
    Route::post('/profile/characters', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'store'])
        ->name('profile.characters.store');
    Route::patch('/profile/characters/{character}', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'update'])
        ->name('profile.characters.update');
    Route::delete('/profile/characters/{character}', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'destroy'])
        ->name('profile.characters.destroy');
    // JSON listing for another CP member — feeds the per-attendee char
    // picker in the loot modal.
    Route::get('/api/users/{user}/characters', [\App\Contexts\Identity\Application\Controllers\CharactersController::class, 'listForUser'])
        ->name('api.users.characters');

    Route::get('/excluded', function (Request $request) {
        $user = $request->user();
        $cp = $user?->cp;
        $leader = $cp?->leader;

        return Inertia::render('Excluded', [
            'cpName' => $cp?->name,
            'leader' => $leader ? [
                'name' => $leader->name,
                'email' => $leader->email,
            ] : null,
        ]);
    })->name('excluded');

    Route::get('/membership/pending', function (Request $request) {
        return Inertia::render('Auth/Pending');
    })->name('membership.pending');

    // Phase 3 & 4 Routes
    Route::get('/party', [PartyController::class, 'index'])->name('party.index');
    Route::get('/warehouse-cp', [PartyController::class, 'index'])->name('party.warehouse_cp')->defaults('tab', 'warehouse_cp');
    // Bulk crafting planner — read-only calculator that aggregates N recipes
    // and crosses them against the CP warehouse stock.
    Route::get('/party/craft-bulk', [\App\Contexts\Loot\Application\Controllers\CraftBulkController::class, 'index'])
        ->name('party.craft_bulk.index');
    Route::post('/api/party/craft-bulk/plan', [\App\Contexts\Loot\Application\Controllers\CraftBulkController::class, 'plan'])
        ->name('party.craft_bulk.plan');
    Route::patch('/party/members/{user}/approve', [PartyController::class, 'approveMember'])->name('party.members.approve');
    Route::post('/party/points/reset', [PartyController::class, 'resetPoints'])->name('party.points.reset');
    Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index');
    Route::patch('/system/users/{user}/ban', [App\Contexts\System\Application\Controllers\UserManagementController::class, 'banMember'])->name('system.users.ban');
    Route::patch('/system/users/{user}/unban', [App\Contexts\System\Application\Controllers\UserManagementController::class, 'unbanMember'])->name('system.users.unban');
    Route::get('/warehouse', [PartyController::class, 'myWarehouse'])->name('warehouse.index');
    Route::get('/loot', [LootController::class, 'index'])->name('loot.index');
    Route::post('/admin/cp', [ConstPartyController::class, 'store'])->name('admin.cp.store');
    Route::post('/admin/cp/{cp}/toggle-active', [ConstPartyController::class, 'toggleActive'])->name('admin.cp.toggleActive');
    Route::delete('/admin/cp/{cp}', [ConstPartyController::class, 'destroy'])->name('admin.cp.destroy');
    // Full admin CP roster (search/filter/edit) — replaces the dashboard widget.
    Route::get('/system/cps', [ConstPartyController::class, 'adminIndex'])->name('system.cps.index');
    Route::patch('/system/cps/{cp}', [ConstPartyController::class, 'adminUpdate'])->name('system.cps.update');
    Route::post('/admin/cp-requests/{cpRequest}/approve', [SupportController::class, 'approveCpRequest'])->name('admin.cp-requests.approve');
    Route::post('/admin/cp-requests/{cpRequest}/reject', [SupportController::class, 'rejectCpRequest'])->name('admin.cp-requests.reject');

    // System Translations (Admin Only)
    Route::get('/system/translations', [TranslationController::class, 'index'])->name('system.translations.index');
    Route::post('/system/translations', [TranslationController::class, 'store'])->name('system.translations.store');
    Route::put('/system/translations/key/{key}', [TranslationController::class, 'updateKey'])->name('system.translations.update_key');
    Route::delete('/system/translations/key/{key}', [TranslationController::class, 'destroyKey'])->name('system.translations.destroy_key');
    Route::put('/system/translations/{translation}', [TranslationController::class, 'update'])->name('system.translations.update');
    Route::delete('/system/translations/{translation}', [TranslationController::class, 'destroy'])->name('system.translations.destroy');

    // CP Management (Admin Perspective)
    Route::get('/admin/cp/{cp}', function (ConstParty $cp) {
        $cpId = $cp->id;

        $stats = [
            'total_cps' => 1,
            'total_members' => User::where('cp_id', $cpId)->where('membership_status', '!=', 'banned')->count(),
            'total_reports' => LootReport::where('cp_id', $cpId)->count(),
            'pending_reports' => LootReport::where('cp_id', $cpId)->where('status', 'pending')->count(),
            'total_points_cp' => PointsLog::where('cp_id', $cpId)->sum('points'),
            'total_items_cp' => LootEntry::whereHas('report', fn ($q) => $q->where('cp_id', $cpId)->where('status', 'confirmed'))->sum('amount'),
            'total_items' => Item::count(),
            'total_points_global' => PointsLog::sum('points'),
        ];

        $days = collect(range(6, 0))->map(fn ($day) => now()->subDays($day)->format('Y-m-d'));
        $cpActivity = LootReport::where('cp_id', $cpId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $chartData = [
            'labels' => $days->map(fn ($d) => date('D', strtotime($d))),
            'datasets' => [
                [
                    'label' => 'Actividad de CP',
                    'data' => $days->map(fn ($d) => $cpActivity->get($d, 0)),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'selectedCp' => $cp->load('leader', 'members'),
            'chartData' => $chartData,
            'cps' => [],
        ]);
    })->name('admin.cp.view');

    // Item Management (SuperAdmin)
    Route::get('/items-db', [ItemManagementController::class, 'itemsDb'])->name('itemsdb.index');
    Route::get('/system/items', [ItemManagementController::class, 'index'])->name('system.items.index');
    Route::patch('/system/items/{item}', [ItemManagementController::class, 'update'])->name('system.items.update');
    Route::delete('/system/items/{item}', [ItemManagementController::class, 'destroy'])->name('system.items.destroy');

    // Releases & Crashes (Admin Only)
    Route::get('/system/releases', [\App\Contexts\System\Application\Controllers\ReleasesController::class, 'index'])
        ->name('system.releases.index');
    Route::post('/system/releases', [\App\Contexts\System\Application\Controllers\ReleasesController::class, 'store'])
        ->name('system.releases.store');
    Route::post('/system/releases/{release}/toggle-publish', [\App\Contexts\System\Application\Controllers\ReleasesController::class, 'togglePublish'])
        ->name('system.releases.toggle_publish');
    Route::delete('/system/releases/{release}', [\App\Contexts\System\Application\Controllers\ReleasesController::class, 'destroy'])
        ->name('system.releases.destroy');

    Route::get('/system/crashes', [\App\Contexts\System\Application\Controllers\CrashesController::class, 'index'])
        ->name('system.crashes.index');
    Route::get('/system/crashes/{fingerprint}', [\App\Contexts\System\Application\Controllers\CrashesController::class, 'show'])
        ->where('fingerprint', '[a-f0-9]{64}')
        ->name('system.crashes.show');
    Route::delete('/system/crashes/{fingerprint}', [\App\Contexts\System\Application\Controllers\CrashesController::class, 'destroy'])
        ->where('fingerprint', '[a-f0-9]{64}')
        ->name('system.crashes.destroy');

    // External payouts (leaders + admin) — adena owed to non-CP attendees
    // that the leader needs to settle outside the system.
    Route::get('/system/external-payouts', [\App\Contexts\Party\Application\Controllers\ExternalPayoutsController::class, 'index'])
        ->name('system.external_payouts.index');
    Route::post('/system/external-payouts/{attendee}/mark-paid', [\App\Contexts\Party\Application\Controllers\ExternalPayoutsController::class, 'markPaid'])
        ->name('system.external_payouts.mark_paid');

    // User Management (Admin & CP Leader Audit)
    Route::get('/system/users', [UserManagementController::class, 'index'])->name('system.users.index');
    Route::get('/system/users/{user}/logs', [UserManagementController::class, 'logs'])->name('system.users.logs');
    Route::patch('/system/users/{user}', [UserManagementController::class, 'update'])->name('system.users.update');
    Route::delete('/system/users/{user}', [UserManagementController::class, 'destroy'])->name('system.users.destroy');

    // Adena Ledger
    Route::post('/adena/transaction', [AdenaActionController::class, 'store'])->name('adena.transaction.store');
    Route::post('/adena/donate', [AdenaActionController::class, 'donate'])->name('adena.donate');

    Route::post('/alerts/{alert}/read', function (Request $request, $alert) {
        $user = $request->user();
        DB::table('audit_alerts')
            ->where('id', (int) $alert)
            ->where('recipient_user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    })->name('alerts.read');

    Route::post('/alerts/read-all', function (Request $request) {
        $user = $request->user();
        DB::table('audit_alerts')
            ->where('recipient_user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    })->name('alerts.readAll');

    // Phase 7: Loot Registration, Approval & Wishlist
    Route::get('/api/items/search', [LootSearchController::class, 'search'])->name('api.items.search');    // Loot & Session Reports
    Route::get('/api/members/{user}/warehouse', [PartyController::class, 'memberWarehouse'])->name('api.party.member.warehouse');
    Route::get('/api/recipes/search', [CraftingController::class, 'search'])->name('api.recipes.search');
    Route::post('/api/recipes/{recipe}/craft', [CraftingController::class, 'craft'])->name('api.recipes.craft');
    Route::get('/api/recipes/{recipe}/tree', [CraftingController::class, 'tree'])->name('api.recipes.tree');
    Route::get('/loot', [LootController::class, 'index'])->name('loot.index');
    Route::post('/loot/report', [LootActionController::class, 'store'])->name('loot.report.store');
    Route::post('/loot/report/{report}/resolve', [LootActionController::class, 'resolve'])->name('loot.report.resolve');
    Route::post('/warehouse/assign', [PartyController::class, 'assign'])->name('warehouse.assign');
    Route::post('/warehouse/return', [PartyController::class, 'requestReturn'])->name('warehouse.return');
    Route::post('/warehouse/add', [PartyController::class, 'addStock'])->name('warehouse.add');
    Route::post('/warehouse/buy', [PartyController::class, 'buyStock'])->name('warehouse.buy');
    Route::post('/warehouse/sell', [PartyController::class, 'sell'])->name('warehouse.sell');
    Route::get('/api/warehouse/sell/default-recipients', [PartyController::class, 'defaultSellRecipients'])->name('api.warehouse.sell.defaultRecipients');
    Route::get('/api/warehouse/sell/source-candidates', [PartyController::class, 'sellSourceCandidates'])->name('api.warehouse.sell.sourceCandidates');
    Route::post('/cp/recipes', [CraftingController::class, 'store'])->name('cp.recipes.store');
    Route::post('/cp/recipes/{cpRecipe}/move', [CraftingController::class, 'move'])->name('cp.recipes.move');
    Route::delete('/cp/recipes/{cpRecipe}', [CraftingController::class, 'destroy'])->name('cp.recipes.destroy');

    // CP Settings & Identity
    Route::post('/cp/settings', [ConstPartyController::class, 'update'])->name('cp.settings.update');
    Route::post('/cp/event-config', [CpEventConfigController::class, 'update'])->name('cp.event-config.update');

    // Wishlist
    Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');
});

require __DIR__.'/auth.php';
