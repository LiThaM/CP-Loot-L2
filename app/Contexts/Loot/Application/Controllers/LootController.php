<?php

namespace App\Contexts\Loot\Application\Controllers;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\CpEventConfig;
use App\Contexts\Loot\Domain\Models\Item;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\Wishlist;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LootController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->cp_id) {
            return Inertia::render('Loot/Index', [
                'has_cp' => false,
            ]);
        }

        // Load Pending Sessions (Reports)
        $pendingLoot = LootReport::with(['entries.item', 'requestedBy'])
            ->where('cp_id', $user->cp_id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        // Load History (Recent Confirmed/Rejected Reports)
        $history = LootReport::with(['entries.item', 'requestedBy'])
            ->where('cp_id', $user->cp_id)
            ->whereIn('status', ['confirmed', 'rejected'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($report) {
                $ids = is_array($report->recipient_ids) ? $report->recipient_ids : [];
                $report->recipients = $ids ? User::whereIn('id', $ids)->get(['id', 'name']) : collect();

                if (in_array($report->event_type, ['ADENA_PAYOUT', 'ADENA_GRANT'], true) && $report->entries->count() === 0) {
                    $adenaItem = Item::whereRaw('LOWER(name) = ?', ['adena'])->first();
                    if ($adenaItem) {
                        $log = PointsLog::where('cp_id', $report->cp_id)
                            ->where('description', 'like', '%Reporte #'.$report->id.'%')
                            ->orderBy('created_at')
                            ->first();

                        if ($log) {
                            $report->setRelation('entries', collect([
                                [
                                    'id' => 'virtual-'.$report->id,
                                    'item' => $adenaItem,
                                    'amount' => abs((int) $log->adena),
                                ],
                            ]));
                        }
                    }
                }

                if (in_array($report->event_type, ['SELL', 'ASSIGN'], true)) {
                    $itemEntry = $report->entries->first(function ($e) {
                        $name = strtolower((string) ($e->item?->name ?? ''));

                        return $name !== '' && $name !== 'adena';
                    });

                    if ($itemEntry) {
                        $origin = LootReport::query()
                            ->select('loot_reports.*')
                            ->join('loot_entries', 'loot_entries.loot_report_id', '=', 'loot_reports.id')
                            ->where('loot_reports.cp_id', $report->cp_id)
                            ->where('loot_reports.status', 'confirmed')
                            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'RETURN'])
                            ->where('loot_entries.item_id', $itemEntry->item_id)
                            ->orderByDesc('loot_reports.id')
                            ->with('requestedBy:id,name')
                            ->first();

                        $report->origin = $origin ? [
                            'id' => $origin->id,
                            'event_type' => $origin->event_type,
                            'created_at' => $origin->created_at,
                            'requested_by' => $origin->requestedBy?->name,
                        ] : null;
                    } else {
                        $report->origin = null;
                    }
                }

                return $report;
            });

        $focusReportId = (int) $request->query('report', 0);
        if ($focusReportId > 0 && ! $history->contains(fn ($r) => $r->id === $focusReportId)) {
            $focusReport = LootReport::with(['entries.item', 'requestedBy'])
                ->where('cp_id', $user->cp_id)
                ->whereIn('status', ['confirmed', 'rejected'])
                ->where('id', $focusReportId)
                ->first();

            if ($focusReport) {
                $ids = is_array($focusReport->recipient_ids) ? $focusReport->recipient_ids : [];
                $focusReport->recipients = $ids ? User::whereIn('id', $ids)->get(['id', 'name']) : collect();

                if (in_array($focusReport->event_type, ['ADENA_PAYOUT', 'ADENA_GRANT'], true) && $focusReport->entries->count() === 0) {
                    $adenaItem = Item::whereRaw('LOWER(name) = ?', ['adena'])->first();
                    if ($adenaItem) {
                        $log = PointsLog::where('cp_id', $focusReport->cp_id)
                            ->where('description', 'like', '%Reporte #'.$focusReport->id.'%')
                            ->orderBy('created_at')
                            ->first();

                        if ($log) {
                            $focusReport->setRelation('entries', collect([
                                [
                                    'id' => 'virtual-'.$focusReport->id,
                                    'item' => $adenaItem,
                                    'amount' => abs((int) $log->adena),
                                ],
                            ]));
                        }
                    }
                }

                if (in_array($focusReport->event_type, ['SELL', 'ASSIGN'], true)) {
                    $itemEntry = $focusReport->entries->first(function ($e) {
                        $name = strtolower((string) ($e->item?->name ?? ''));

                        return $name !== '' && $name !== 'adena';
                    });

                    if ($itemEntry) {
                        $origin = LootReport::query()
                            ->select('loot_reports.*')
                            ->join('loot_entries', 'loot_entries.loot_report_id', '=', 'loot_reports.id')
                            ->where('loot_reports.cp_id', $focusReport->cp_id)
                            ->where('loot_reports.status', 'confirmed')
                            ->whereNotIn('loot_reports.event_type', ['ASSIGN', 'SELL', 'RETURN'])
                            ->where('loot_entries.item_id', $itemEntry->item_id)
                            ->orderByDesc('loot_reports.id')
                            ->with('requestedBy:id,name')
                            ->first();

                        $focusReport->origin = $origin ? [
                            'id' => $origin->id,
                            'event_type' => $origin->event_type,
                            'created_at' => $origin->created_at,
                            'requested_by' => $origin->requestedBy?->name,
                        ] : null;
                    } else {
                        $focusReport->origin = null;
                    }
                }

                $history = collect([$focusReport])->concat($history)->values();
            }
        }

        $wishlist = Wishlist::with('item')
            ->where('cp_id', $user->cp_id)
            ->get();

        $members = User::where('cp_id', $user->cp_id)
            ->where('membership_status', '!=', 'banned')
            ->orderBy('name')
            ->get(['id', 'name']);

        // CP Event Configuration for the Leader
        $eventConfigs = CpEventConfig::where('cp_id', $user->cp_id)->get();

        return Inertia::render('Loot/Index', [
            'has_cp' => true,
            'pendingLoot' => $pendingLoot,
            'history' => $history,
            'wishlist' => $wishlist,
            'members' => $members,
            'eventConfigs' => $eventConfigs,
            'isLeader' => $user->cp->leader_id === $user->id,
        ]);
    }
}
