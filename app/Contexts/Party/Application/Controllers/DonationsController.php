<?php

namespace App\Contexts\Party\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationsController extends Controller
{
    /**
     * A member donates an item to the CP. Recorded as a PENDING LootReport
     * (event_type=DONATION) so it shows in /loot and the leader reviews it.
     * On confirm: tracker CPs award the donor DKP points (value ÷ divisor ×
     * objective multiplier); non-tracker CPs just bank the item. See
     * LootActionController@resolve + TrackerContributionService.
     */
    public function donateItem(Request $request)
    {
        $user = $request->user();
        if (! $user->cp_id) {
            abort(403, 'No perteneces a ninguna CP.');
        }

        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'image_proof' => ['nullable', 'image', 'max:3072'],
        ]);

        $this->createDonationReport($user, (int) $data['item_id'], (int) $data['quantity'], $request->file('image_proof'));

        return back()->with('success', 'Donación registrada. Pendiente de aprobación del líder.');
    }

    /**
     * A member donates adena to the CP — modelled as a DONATION LootReport
     * whose single entry is the "Adena" item of the CP's chronicle.
     */
    public function donateAdena(Request $request)
    {
        $user = $request->user();
        if (! $user->cp_id) {
            abort(403, 'No perteneces a ninguna CP.');
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'image_proof' => ['nullable', 'image', 'max:3072'],
        ]);

        $adenaItem = $this->adenaItemFor($user->cp);
        if (! $adenaItem) {
            return back()->withErrors(['amount' => 'No hay un item "Adena" configurado para tu crónica.']);
        }

        $this->createDonationReport($user, $adenaItem->id, (int) $data['amount'], $request->file('image_proof'));

        return back()->with('success', 'Donación de adena registrada. Pendiente de aprobación del líder.');
    }

    private function createDonationReport(User $user, int $itemId, int $amount, $imageFile = null): void
    {
        DB::transaction(function () use ($user, $itemId, $amount, $imageFile) {
            $report = LootReport::create([
                'cp_id' => $user->cp_id,
                'requested_by_id' => $user->id,
                'event_type' => 'DONATION',
                'status' => 'pending',
                'image_proof' => null,
                // The donor is the report's single attendee/recipient, so the
                // loot UI shows "who donated" everywhere (count, expanded view,
                // pending review) like any other report.
                'recipient_ids' => [$user->id],
            ]);

            if ($imageFile) {
                $ext = $imageFile->extension() ?: ($imageFile->guessExtension() ?: 'jpg');
                $path = $imageFile->storeAs("loot/{$user->cp_id}", "{$report->id}.{$ext}", 'public');
                $report->image_proof = $path;
                $report->save();
            }

            LootEntry::create([
                'loot_report_id' => $report->id,
                'item_id' => $itemId,
                'amount' => $amount,
            ]);

            LootReportAttendee::create([
                'loot_report_id' => $report->id,
                'user_id' => $user->id,
                'external_name' => null,
                'is_external' => false,
            ]);
        });
    }

    private function adenaItemFor($cp): ?Item
    {
        $base = Item::whereRaw('LOWER(name) = ?', ['adena']);
        if ($cp && $cp->chronicle) {
            $byChronicle = (clone $base)->where('chronicle', $cp->chronicle)->first();
            if ($byChronicle) {
                return $byChronicle;
            }
        }

        return $base->first();
    }
}
