<?php

namespace App\Contexts\Loot\Domain\Services;

use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Party\Domain\Models\PointsLog;
use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

class LootDistributionService
{
    /**
     * Distribute points from a loot report across multiple participants.
     * Each participant receives the FULL points per member defined for the event.
     *
     * @return void
     */
    public function distribute(LootReport $report, array $memberIds, int $pointsPerMember)
    {
        if (empty($memberIds)) {
            return;
        }

        // Filter out banned members
        $validMemberIds = User::where('cp_id', $report->cp_id)
            ->where('membership_status', '!=', 'banned')
            ->whereIn('id', $memberIds)
            ->pluck('id')
            ->all();

        if (empty($validMemberIds)) {
            return;
        }

        DB::transaction(function () use ($report, $validMemberIds, $pointsPerMember) {
            foreach ($validMemberIds as $memberId) {
                PointsLog::create([
                    'cp_id' => $report->cp_id,
                    'user_id' => $memberId,
                    'action_type' => $report->event_type,
                    'points' => $pointsPerMember,
                    'description' => "Participación en raid: {$report->event_type}",
                ]);
            }

            // Update report with final distribution details
            $report->update([
                'status' => 'confirmed',
                'recipient_ids' => $validMemberIds,
                'points_per_member' => $pointsPerMember,
            ]);
        });
    }
}
