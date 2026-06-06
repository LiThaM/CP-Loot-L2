<?php

namespace App\Contexts\Party\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use App\Contexts\Loot\Domain\Models\LootEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Ledger row for the value-based DKP tracker. Each row represents an
 * amount of points credited to a single member, either auto-derived
 * from a confirmed LootEntry (badge SOLO / PARTY/N) or logged manually
 * by the leader as an EVENT bonus.
 *
 * This model lives alongside (not replacing) `PointsLog` — the existing
 * event-based system keeps running. The two ledgers are intentionally
 * separate so a leader can opt into the value-based mode without
 * disturbing historic points.
 */
class TrackerContribution extends Model
{
    public const TYPE_MATERIAL = 'material';
    public const TYPE_EVENT = 'event';

    public const BADGE_SOLO = 'SOLO';
    public const BADGE_EVENT = 'EVENT';
    public const BADGE_PARTY_PREFIX = 'PARTY/';

    protected $fillable = [
        'cp_id',
        'user_id',
        'type',
        'points',
        'description',
        'badge',
        'source_loot_entry_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'points' => 'decimal:2',
    ];

    public function cp()
    {
        return $this->belongsTo(ConstParty::class, 'cp_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sourceLootEntry()
    {
        return $this->belongsTo(LootEntry::class, 'source_loot_entry_id');
    }
}
