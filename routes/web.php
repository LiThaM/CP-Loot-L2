<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\DashboardController;

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

use App\Contexts\Party\Application\Controllers\PartyController;
use App\Contexts\Party\Application\Controllers\ConstPartyController;
use App\Contexts\Loot\Application\Controllers\LootController;
use App\Contexts\System\Application\Controllers\TranslationController;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Phase 3 & 4 Routes
    Route::get('/party', [PartyController::class, 'index'])->name('party.index');
    Route::get('/loot', [LootController::class, 'index'])->name('loot.index');
    Route::post('/admin/cp', [ConstPartyController::class, 'store'])->name('admin.cp.store');

    // System Translations (Admin Only)
    Route::get('/system/translations', [TranslationController::class, 'index'])->name('system.translations.index');
    Route::post('/system/translations', [TranslationController::class, 'store'])->name('system.translations.store');
    Route::put('/system/translations/{translation}', [TranslationController::class, 'update'])->name('system.translations.update');
    Route::delete('/system/translations/{translation}', [TranslationController::class, 'destroy'])->name('system.translations.destroy');

    // CP Management (Admin Perspective)
    Route::get('/admin/cp/{cp}', function (\App\Contexts\Party\Domain\Models\ConstParty $cp) {
        return Inertia::render('Dashboard', [
            'stats' => [
                'total_cps' => 1,
                'total_items' => 0,
                'pending_tx' => 0,
            ],
            'selectedCp' => $cp->load('leader', 'members')
        ]);
    })->name('admin.cp.view');

    // Item Management (SuperAdmin)
    Route::get('/system/items', [App\Contexts\System\Application\Controllers\ItemManagementController::class, 'index'])->name('system.items.index');
    Route::patch('/system/items/{item}', [App\Contexts\System\Application\Controllers\ItemManagementController::class, 'update'])->name('system.items.update');
    Route::delete('/system/items/{item}', [App\Contexts\System\Application\Controllers\ItemManagementController::class, 'destroy'])->name('system.items.destroy');

    // User Management (Admin & CP Leader Audit)
    Route::get('/system/users', [App\Contexts\System\Application\Controllers\UserManagementController::class, 'index'])->name('system.users.index');
    Route::patch('/system/users/{user}', [App\Contexts\System\Application\Controllers\UserManagementController::class, 'update'])->name('system.users.update');
    Route::delete('/system/users/{user}', [App\Contexts\System\Application\Controllers\UserManagementController::class, 'destroy'])->name('system.users.destroy');

    // Adena Ledger
    Route::post('/adena/transaction', [App\Contexts\Loot\Application\Controllers\AdenaActionController::class, 'store'])->name('adena.transaction.store');

    // Phase 7: Loot Registration, Approval & Wishlist
    Route::get('/api/items/search', [App\Contexts\Loot\Application\Controllers\LootSearchController::class, 'search'])->name('api.items.search');    // Loot & Session Reports
    Route::get('/loot', [LootController::class, 'index'])->name('loot.index');
    Route::post('/loot/report', [LootActionController::class, 'store'])->name('loot.report.store');
    Route::post('/loot/report/{report}/resolve', [LootActionController::class, 'resolve'])->name('loot.report.resolve');
    
    // CP Custom Points Config
    Route::post('/cp/event-config', [CpEventConfigController::class, 'update'])->name('cp.event-config.update');
    
    // Wishlist
    Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
});

require __DIR__.'/auth.php';
