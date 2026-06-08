<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Party\Application\Services\AuctionService;
use App\Contexts\Party\Domain\Models\CpAuction;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AuctionController extends Controller
{
    public function __construct(private AuctionService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->cp_id, 403);

        $cpId = $user->cp_id;
        $isLeader = in_array($user->role?->name, ['cp_leader', 'accountant', 'admin'], true);

        $auctions = CpAuction::query()
            ->with(['item:id,name,image_url,grade,market_price,npc_sell_price', 'currentBidder:id,name,avatar_path', 'winner:id,name,avatar_path', 'createdBy:id,name'])
            ->where('cp_id', $cpId)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($a) => $this->transform($a));

        // Member's available balances for the bid widgets.
        $availablePoints = $user->cp?->tracker_enabled
            ? $this->service->availableBalance($user, $cpId, CpAuction::CURRENCY_POINTS)
            : 0;
        $availableAdena = $this->service->availableBalance($user, $cpId, CpAuction::CURRENCY_ADENA);

        return Inertia::render('Party/Auctions', [
            'cp' => [
                'id' => $user->cp->id,
                'name' => $user->cp->name,
                'tracker_enabled' => (bool) $user->cp->tracker_enabled,
                'tracker_divisor' => (int) $user->cp->tracker_divisor,
            ],
            'auctions' => $auctions,
            'isLeader' => $isLeader,
            'me' => [
                'id' => $user->id,
                'available_points' => round($availablePoints, 2),
                'available_adena' => (int) $availableAdena,
            ],
            // Only the leader / accountant / admin can open auctions, so the
            // warehouse list (used by the picker in the "Open auction" modal)
            // is only computed for them. Each entry carries the available
            // stock so the picker can show "x N in vault" and cap the
            // amount field client-side.
            'warehouseItems' => $isLeader ? $this->warehouseItemsInStock($cpId) : [],
        ]);
    }

    /**
     * Items in the CP warehouse with a positive net balance. Mirrors the
     * aggregation used by AuctionService::vaultAvailable but at list-of-
     * items granularity, so the auction "Open" modal can autocomplete on
     * the actual contents of the warehouse instead of the global catalogue.
     */
    private function warehouseItemsInStock(int $cpId): array
    {
        $incoming = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->join('items', 'items.id', '=', 'loot_entries.item_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->whereRaw('LOWER(items.name) != ?', ['adena'])
            ->groupBy('items.id', 'items.name', 'items.image_url', 'items.grade', 'items.market_price', 'items.npc_sell_price')
            ->get([
                'items.id', 'items.name', 'items.image_url', 'items.grade',
                'items.market_price', 'items.npc_sell_price',
                DB::raw('SUM(loot_entries.amount) as incoming'),
            ])
            ->keyBy('id');

        $outgoing = DB::table('loot_entries')
            ->join('loot_reports', 'loot_reports.id', '=', 'loot_entries.loot_report_id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->whereNull('loot_reports.voided_at')
            ->whereIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME', 'WAREHOUSE_RECHECK_LOSS'])
            ->groupBy('loot_entries.item_id')
            ->pluck(DB::raw('SUM(loot_entries.amount) as outgoing'), 'loot_entries.item_id');

        $rows = [];
        foreach ($incoming as $row) {
            $available = max(0, (int) $row->incoming - (int) ($outgoing[$row->id] ?? 0));
            if ($available <= 0) {
                continue;
            }
            $rows[] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'image_url' => $row->image_url,
                'grade' => $row->grade,
                'market_price' => $row->market_price !== null ? (int) $row->market_price : null,
                'npc_sell_price' => $row->npc_sell_price !== null ? (int) $row->npc_sell_price : null,
                'available' => $available,
            ];
        }
        // Sort by name for predictable autocomplete order.
        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        return $rows;
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role?->name, ['cp_leader', 'accountant', 'admin'], true), 403);
        abort_unless($user->cp_id, 403);

        $data = $request->validate([
            'item_id' => 'required|integer|exists:items,id',
            'amount' => 'required|integer|min:1',
            'currency' => 'required|in:points,adena',
            'starting_bid' => 'required|numeric|min:1',
            'buy_now_price' => 'nullable|numeric|min:1',
            'duration_minutes' => 'required|integer|in:15,60,360,1440,4320',
        ]);

        try {
            $item = Item::findOrFail($data['item_id']);
            $this->service->open(
                $user,
                $item,
                (int) $data['amount'],
                $data['currency'],
                (float) $data['starting_bid'],
                $data['buy_now_price'] !== null ? (float) $data['buy_now_price'] : null,
                Carbon::now()->addMinutes((int) $data['duration_minutes']),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['item_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Subasta abierta.');
    }

    public function bid(Request $request, CpAuction $auction): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->cp_id === $auction->cp_id, 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $this->service->bid($user, $auction, (float) $data['amount']);
        } catch (Throwable $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Puja registrada.');
    }

    public function fulfill(Request $request, CpAuction $auction): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role?->name, ['cp_leader', 'accountant', 'admin'], true), 403);
        abort_unless($user->cp_id === $auction->cp_id, 403);

        try {
            $this->service->fulfill($auction, $user);
        } catch (Throwable $e) {
            return back()->withErrors(['fulfill' => $e->getMessage()]);
        }

        return back()->with('success', 'Subasta entregada.');
    }

    public function cancel(Request $request, CpAuction $auction): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role?->name, ['cp_leader', 'accountant', 'admin'], true), 403);
        abort_unless($user->cp_id === $auction->cp_id, 403);

        try {
            $this->service->cancel($auction, $user);
        } catch (Throwable $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return back()->with('success', 'Subasta cancelada.');
    }

    private function transform(CpAuction $a): array
    {
        return [
            'id' => $a->id,
            'item' => $a->item ? [
                'id' => $a->item->id,
                'name' => $a->item->name,
                'image_url' => $a->item->image_url,
                'grade' => $a->item->grade,
            ] : null,
            'amount' => (int) $a->amount,
            'currency' => $a->currency,
            'starting_bid' => (float) $a->starting_bid,
            'buy_now_price' => $a->buy_now_price !== null ? (float) $a->buy_now_price : null,
            'current_bid' => $a->current_bid !== null ? (float) $a->current_bid : null,
            'current_bidder' => $a->currentBidder ? [
                'id' => $a->currentBidder->id,
                'name' => $a->currentBidder->name,
                'avatar_url' => $a->currentBidder->avatar_url,
            ] : null,
            'winner' => $a->winner ? [
                'id' => $a->winner->id,
                'name' => $a->winner->name,
                'avatar_url' => $a->winner->avatar_url,
            ] : null,
            'created_by' => $a->createdBy?->name,
            'ends_at' => $a->ends_at?->toIso8601String(),
            'status' => $a->status,
            'fulfilled_at' => $a->fulfilled_at?->toIso8601String(),
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
}
