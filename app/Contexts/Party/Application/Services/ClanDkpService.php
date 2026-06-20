<?php

namespace App\Contexts\Party\Application\Services;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Party\Domain\Models\Clan;
use App\Contexts\Party\Domain\Models\ClanDkpAdjustment;
use App\Contexts\Party\Domain\Models\ClanEventAttendee;
use App\Contexts\Party\Domain\Models\ClanVaultAuction;
use Illuminate\Support\Facades\DB;

class ClanDkpService
{
    /**
     * Total clan DKP earned by a user: approved event attendances + positive adjustments.
     */
    public function earned(User $user, Clan $clan): int
    {
        $fromEvents = ClanEventAttendee::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->join('clan_events', 'clan_events.id', '=', 'clan_event_attendees.clan_event_id')
            ->where('clan_events.clan_id', $clan->id)
            ->where('clan_events.status', 'finalized')
            ->sum('clan_events.dkp_reward');

        $fromAdjustments = ClanDkpAdjustment::query()
            ->where('user_id', $user->id)
            ->where('clan_id', $clan->id)
            ->where('amount', '>', 0)
            ->sum('amount');

        return (int) $fromEvents + (int) $fromAdjustments;
    }

    /**
     * Total clan DKP spent by a user: won auctions + negative adjustments.
     */
    public function spent(User $user, Clan $clan): int
    {
        $fromAuctions = ClanVaultAuction::query()
            ->where('winner_user_id', $user->id)
            ->where('status', 'closed')
            ->whereHas('vaultItem', fn ($q) => $q->where('clan_id', $clan->id))
            ->sum('winning_bid');

        $fromAdjustments = ClanDkpAdjustment::query()
            ->where('user_id', $user->id)
            ->where('clan_id', $clan->id)
            ->where('amount', '<', 0)
            ->sum(DB::raw('ABS(amount)'));

        return (int) $fromAuctions + (int) $fromAdjustments;
    }

    /**
     * Net DKP balance for a user in the clan.
     */
    public function balance(User $user, Clan $clan): int
    {
        return $this->earned($user, $clan) - $this->spent($user, $clan);
    }

    /**
     * Build a map of user_id → balance for all clan members in one pass.
     * More efficient than calling balance() N times.
     */
    public function balanceMapForClan(Clan $clan): array
    {
        $clanId = $clan->id;

        // DKP from approved event attendances
        $eventDkp = ClanEventAttendee::query()
            ->select('clan_event_attendees.user_id', DB::raw('SUM(clan_events.dkp_reward) as total'))
            ->join('clan_events', 'clan_events.id', '=', 'clan_event_attendees.clan_event_id')
            ->where('clan_events.clan_id', $clanId)
            ->where('clan_events.status', 'finalized')
            ->where('clan_event_attendees.status', 'approved')
            ->whereNotNull('clan_event_attendees.user_id')
            ->groupBy('clan_event_attendees.user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // DKP from positive adjustments
        $posAdj = ClanDkpAdjustment::query()
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->where('clan_id', $clanId)
            ->where('amount', '>', 0)
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // DKP spent via auctions
        $auctionSpent = ClanVaultAuction::query()
            ->select('winner_user_id', DB::raw('SUM(winning_bid) as total'))
            ->where('status', 'closed')
            ->whereHas('vaultItem', fn ($q) => $q->where('clan_id', $clanId))
            ->whereNotNull('winner_user_id')
            ->groupBy('winner_user_id')
            ->pluck('total', 'winner_user_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // DKP spent via negative adjustments
        $negAdj = ClanDkpAdjustment::query()
            ->select('user_id', DB::raw('SUM(ABS(amount)) as total'))
            ->where('clan_id', $clanId)
            ->where('amount', '<', 0)
            ->groupBy('user_id')
            ->pluck('total', 'user_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // Merge into balance map
        $userIds = array_unique(array_merge(
            array_keys($eventDkp),
            array_keys($posAdj),
            array_keys($auctionSpent),
            array_keys($negAdj)
        ));

        $balances = [];
        foreach ($userIds as $uid) {
            $earned = ($eventDkp[$uid] ?? 0) + ($posAdj[$uid] ?? 0);
            $spent  = ($auctionSpent[$uid] ?? 0) + ($negAdj[$uid] ?? 0);
            $balances[$uid] = $earned - $spent;
        }

        return $balances;
    }
}
