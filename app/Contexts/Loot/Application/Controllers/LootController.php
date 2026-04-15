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
use Illuminate\Support\Facades\DB;
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

        $historySearch = trim((string) $request->query('history_search', ''));
        $historySort = strtolower((string) $request->query('history_sort', 'newest'));
        $historyPerPage = max(5, min(50, (int) $request->query('history_per_page', 10)));
        $historyPage = max(1, (int) $request->query('history_page', 1));
        $historyDir = $historySort === 'oldest' ? 'asc' : 'desc';

        $historyQuery = LootReport::with(['entries.item', 'requestedBy'])
            ->where('cp_id', $user->cp_id)
            ->whereIn('status', ['confirmed', 'rejected']);

        if ($historySearch !== '') {
            $numericId = ctype_digit($historySearch) ? (int) $historySearch : null;
            $historyQuery->where(function ($q) use ($historySearch, $numericId) {
                if ($numericId) {
                    $q->orWhere('loot_reports.id', $numericId);
                }
                $q->orWhereHas('requestedBy', function ($q2) use ($historySearch) {
                    $q2->where('name', 'like', '%'.$historySearch.'%');
                });
                $q->orWhereHas('entries.item', function ($q3) use ($historySearch) {
                    $q3->where('items.name', 'like', '%'.$historySearch.'%');
                });
            });
        }

        // Load History (Confirmed/Rejected Reports) with pagination
        $historyPaginator = $historyQuery
            ->orderBy('updated_at', $historyDir)
            ->paginate($historyPerPage, ['*'], 'history_page', $historyPage);

        $historyPaginator->setCollection(
            $historyPaginator->getCollection()->map(function ($report) {
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
                        $origin = $this->findWarehouseOriginForOutgoing(
                            (int) $report->cp_id,
                            (int) $itemEntry->item_id,
                            (int) $report->id
                        );
                        $report->origin = $origin;
                    } else {
                        $report->origin = null;
                    }
                }

                return $report;
            })
        );

        $history = $historyPaginator->items();

        $focusReportId = (int) $request->query('report', 0);
        if ($focusReportId > 0 && ! collect($history)->contains(fn ($r) => (int) $r->id === $focusReportId)) {
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
                        $origin = $this->findWarehouseOriginForOutgoing(
                            (int) $focusReport->cp_id,
                            (int) $itemEntry->item_id,
                            (int) $focusReport->id
                        );
                        $focusReport->origin = $origin;
                    } else {
                        $focusReport->origin = null;
                    }
                }

                $history = collect([$focusReport])->concat(collect($history))->unique('id')->values()->all();
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
            'historyPagination' => [
                'page' => $historyPaginator->currentPage(),
                'per_page' => $historyPaginator->perPage(),
                'total' => $historyPaginator->total(),
                'has_more' => $historyPaginator->hasMorePages(),
            ],
            'wishlist' => $wishlist,
            'members' => $members,
            'eventConfigs' => $eventConfigs,
            'isLeader' => $user->cp->leader_id === $user->id,
        ]);
    }

    private function findWarehouseOriginForOutgoing(int $cpId, int $itemId, int $beforeReportId): ?array
    {
        $rows = LootReport::query()
            ->select([
                'loot_reports.id',
                'loot_reports.event_type',
                'loot_reports.created_at',
                'loot_reports.requested_by_id',
                DB::raw('SUM(loot_entries.amount) as item_amount'),
            ])
            ->join('loot_entries', 'loot_entries.loot_report_id', '=', 'loot_reports.id')
            ->where('loot_reports.cp_id', $cpId)
            ->where('loot_reports.status', 'confirmed')
            ->where('loot_reports.id', '<', $beforeReportId)
            ->where('loot_entries.item_id', $itemId)
            ->groupBy('loot_reports.id', 'loot_reports.event_type', 'loot_reports.created_at', 'loot_reports.requested_by_id')
            ->orderBy('loot_reports.id')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $outgoingTypes = ['ASSIGN', 'SELL', 'WAREHOUSE_CRAFT_CONSUME'];

        $stack = [];
        foreach ($rows as $row) {
            $qty = max(0, (int) $row->item_amount);
            if ($qty === 0) {
                continue;
            }

            if (in_array((string) $row->event_type, $outgoingTypes, true)) {
                $remaining = $qty;
                while ($remaining > 0 && count($stack) > 0) {
                    $idx = count($stack) - 1;
                    $take = min($remaining, (int) $stack[$idx]['remaining']);
                    $stack[$idx]['remaining'] = (int) $stack[$idx]['remaining'] - $take;
                    $remaining -= $take;
                    if ((int) $stack[$idx]['remaining'] <= 0) {
                        array_pop($stack);
                    }
                }
                continue;
            }

            $stack[] = [
                'report_id' => (int) $row->id,
                'event_type' => (string) $row->event_type,
                'created_at' => $row->created_at,
                'requested_by_id' => (int) $row->requested_by_id,
                'remaining' => $qty,
            ];
        }

        if (count($stack) === 0) {
            return null;
        }

        $top = $stack[count($stack) - 1];
        $requestedByName = null;
        if ((int) $top['requested_by_id'] > 0) {
            $requestedByName = User::where('id', (int) $top['requested_by_id'])->value('name');
        }

        return [
            'id' => (int) $top['report_id'],
            'event_type' => (string) $top['event_type'],
            'created_at' => $top['created_at'],
            'requested_by' => $requestedByName,
        ];
    }
}
