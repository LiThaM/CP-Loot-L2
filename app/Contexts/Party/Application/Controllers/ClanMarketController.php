<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanCpMembership;
use App\Contexts\Party\Domain\Models\ClanMarketListing;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClanMarketController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');

        $listings = ClanMarketListing::where('clan_id', $clan->id)
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($l) => [
                'id'            => $l->id,
                'user_id'       => $l->user_id,
                'user_name'     => $l->user?->name,
                'listing_type'  => $l->listing_type,
                'item_type'     => $l->item_type,
                'item_name'     => $l->item_name,
                'quantity'      => $l->quantity,
                'price'         => $l->price,
                'is_negotiable' => (bool) $l->is_negotiable,
                'contact_info'  => $l->contact_info,
                'notes'         => $l->notes,
                'status'        => $l->status,
                'created_at'    => $l->created_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        return Inertia::render('Clan/Market', [
            'listings'       => $listings,
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

        $data = $request->validate([
            'listing_type' => ['required', 'string', 'in:wts,wtb'],
            'item_type'    => ['string', 'in:item,account'],
            'item_name'    => ['required', 'string', 'max:100'],
            'item_id'      => ['nullable', 'integer'],
            'quantity'     => ['integer', 'min:1'],
            'price'        => ['nullable', 'integer', 'min:0'],
            'is_negotiable' => ['boolean'],
            'contact_info' => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        ClanMarketListing::create([
            'clan_id'       => $clan->id,
            'user_id'       => $user->id,
            'listing_type'  => $data['listing_type'],
            'item_type'     => $data['item_type'] ?? 'item',
            'item_name'     => $data['item_name'],
            'item_id'       => $data['item_id'] ?? null,
            'quantity'      => $data['quantity'] ?? 1,
            'price'         => $data['price'] ?? null,
            'is_negotiable' => $data['is_negotiable'] ?? false,
            'contact_info'  => $data['contact_info'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'status'        => 'active',
        ]);

        return back()->with('success', 'Anuncio publicado en el mercado.');
    }

    public function update(Request $request, ClanMarketListing $listing): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($listing->clan_id === $clan->id, 403, 'Este anuncio no pertenece a tu clan.');

        $isOwner = $listing->user_id === $user->id;
        abort_unless($isOwner || $membership->isAdmin(), 403, 'Solo el autor o un administrador pueden modificar este anuncio.');

        $data = $request->validate([
            'status' => ['required', 'string', 'in:active,sold,cancelled'],
        ]);

        $listing->status = $data['status'];
        $listing->save();

        return back()->with('success', 'Anuncio actualizado.');
    }

    public function destroy(Request $request, ClanMarketListing $listing): RedirectResponse
    {
        $user = $request->user();
        $cp = $user->cp;
        $membership = $cp ? ClanCpMembership::where('cp_id', $cp->id)->first() : null;
        $clan = $membership ? Clan::find($membership->clan_id) : null;
        abort_unless($clan, 403, 'No perteneces a ningún clan.');
        abort_unless($listing->clan_id === $clan->id, 403, 'Este anuncio no pertenece a tu clan.');

        $isOwner = $listing->user_id === $user->id;
        abort_unless($isOwner || $membership->isAdmin(), 403, 'Solo el autor o un administrador pueden eliminar este anuncio.');

        $listing->delete();

        return back()->with('success', 'Anuncio eliminado.');
    }
}
