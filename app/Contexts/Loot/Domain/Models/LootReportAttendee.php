<?php

namespace App\Contexts\Loot\Domain\Models;

use App\Contexts\Identity\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;

class LootReportAttendee extends Model
{
    protected $table = 'loot_report_attendees';

    protected $fillable = [
        'loot_report_id',
        'user_id',
        'character_id',
        'external_name',
        'is_external',
        'share_adena',
        'paid_at',
    ];

    protected $casts = [
        'is_external' => 'boolean',
        'share_adena' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function lootReport()
    {
        return $this->belongsTo(LootReport::class, 'loot_report_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function character()
    {
        return $this->belongsTo(\App\Contexts\Identity\Domain\Models\Character::class, 'character_id');
    }

    public function displayName(): string
    {
        if ($this->is_external) {
            return $this->external_name ?? '(external)';
        }
        return $this->user?->name ?? '(unknown member)';
    }
}
