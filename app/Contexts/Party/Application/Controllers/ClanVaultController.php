<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Application\Services\ClanDkpService;
use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanVaultAuction;
use App\Contexts\Party\Domain\Models\ClanVaultItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClanVaultController extends Controller
{
    public function __construct(private readonly ClanDkpService $dkpService) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');

        $items = ClanVaultItem::where('clan_id', $clan->id)
            ->with(['item', 'assignedToCp:id,name', 'depositedBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($i) => [
                'id'                  => $i->id,
                'item_name'           => $i->item_name,
                'item_image_url'      => $i->item_image_url ?? $i->item?->image_url,
                'quantity'            => $i->quantity,
                'status'              => $i->status,
                'assigned_to_cp_name' => $i->assignedToCp?->name,
                'deposited_by_name'   => $i->depositedBy?->name,
            ])
            ->values()
            ->toArray();

        $auctions = ClanVaultAuction::where('status', ClanVaultAuction::STATUS_OPEN)
            ->whereHas('vaultItem', fn ($q) => $q->where('clan_id', $clan->id))
            ->with(['vaultItem', 'bids.user'])
            ->get()
            ->map(function (ClanVaultAuction $auction) {
                $topBid = $auction->bids->sortByDesc('bid_amount')->first();
                return [
                    'id'             => $auction->id,
                    'item_name'      => $auction->vaultItem?->item_name,
                    'item_image_url' => $auction->vaultItem?->item_image_url,
                    'min_bid'        => $auction->min_bid,
                    'ends_at'        => $auction->ends_at,
                    'highest_bid'    => $topBid?->bid_amount,
                    'highest_bidder' => $topBid?->user?->name,
                ];
            })
            ->values()
            ->toArray();

        $clanCps = ClanCpMembership::where('clan_id', $clan->id)
            ->with('constParty:id,name')
            ->get()
            ->map(fn ($m) => [
                'cp_id'   => $m->cp_id,
                'cp_name' => $m->constParty?->name ?? 'CP #' . $m->cp_id,
            ])
            ->values()
            ->toArray();

        return Inertia::render('Clan/Vault', [
            'items'          => $items,
            'auctions'       => $auctions,
            'clanCps'        => $clanCps,
            'myDkp'          => $this->dkpService->balance($user, $clan),
            'userMembership' => [
                'role' => $membership->role,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden añadir items al cofre.');

        $data = $request->validate([
            'item_name'      => ['required', 'string', 'max:100'],
            'item_id'        => ['nullable', 'integer', 'exists:items,id'],
            'item_image_url' => ['nullable', 'url', 'max:500'],
            'quantity'       => ['integer', 'min:1', 'max:99999'],
        ]);

        ClanVaultItem::create([
            'clan_id'              => $clan->id,
            'item_name'            => $data['item_name'],
            'item_id'              => $data['item_id'] ?? null,
            'item_image_url'       => $data['item_image_url'] ?? null,
            'quantity'             => $data['quantity'] ?? 1,
            'status'               => ClanVaultItem::STATUS_IN_VAULT,
            'deposited_by_user_id' => $user->id,
        ]);

        return back()->with('success', 'Item añadido al cofre.');
    }

    public function assign(Request $request, ClanVaultItem $item): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($item->clan_id === $clan->id, 403, 'Este item no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden asignar items.');

        $data = $request->validate([
            'cp_id' => ['required', 'integer'],
        ]);

        $cpIsInClan = ClanCpMembership::where('clan_id', $clan->id)
            ->where('cp_id', $data['cp_id'])
            ->exists();

        abort_unless($cpIsInClan, 422, 'El CP seleccionado no pertenece a este clan.');

        $item->status = ClanVaultItem::STATUS_ASSIGNED;
        $item->assigned_to_cp_id = $data['cp_id'];
        $item->save();

        return back()->with('success', 'Item asignado al CP.');
    }

    public function raffle(Request $request, ClanVaultItem $item): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($item->clan_id === $clan->id, 403, 'Este item no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden sortear items.');

        $memberships = ClanCpMembership::where('clan_id', $clan->id)->get();
        abort_unless($memberships->isNotEmpty(), 422, 'No hay CPs en el clan para sortear.');

        $winner = $memberships->random();

        $item->status = ClanVaultItem::STATUS_RAFFLED;
        $item->assigned_to_cp_id = $winner->cp_id;
        $item->save();

        return back()->with('success', 'Sorteo realizado. CP ganador: ' . $winner->cp_id . '.');
    }

    public function createAuction(Request $request, ClanVaultItem $item): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($item->clan_id === $clan->id, 403, 'Este item no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden crear subastas.');

        $data = $request->validate([
            'min_bid' => ['integer', 'min:0'],
            'ends_at' => ['required', 'date', 'after:now'],
        ]);

        $item->status = ClanVaultItem::STATUS_AUCTIONING;
        $item->save();

        ClanVaultAuction::create([
            'vault_item_id' => $item->id,
            'min_bid'       => $data['min_bid'] ?? 0,
            'ends_at'       => $data['ends_at'],
            'status'        => ClanVaultAuction::STATUS_OPEN,
        ]);

        return back()->with('success', 'Subasta iniciada.');
    }

    public function destroy(Request $request, ClanVaultItem $item): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($item->clan_id === $clan->id, 403, 'Este item no pertenece a tu clan.');
        abort_unless($membership->isAdmin(), 403, 'Solo administradores pueden eliminar items del cofre.');
        abort_unless(
            in_array($item->status, [ClanVaultItem::STATUS_IN_VAULT, ClanVaultItem::STATUS_REMOVED], true),
            422,
            'Solo se pueden eliminar items en estado "en cofre" o "eliminado".'
        );

        $item->status = ClanVaultItem::STATUS_REMOVED;
        $item->save();

        return back()->with('success', 'Item eliminado del cofre.');
    }
}
