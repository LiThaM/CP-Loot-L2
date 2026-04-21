<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'name',
        'email',
        'status',
        'type',
        'assigned_to_user_id',
        'ticket_number',
        'closed_at',
        'metadata',
        'attachments',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'attachments' => 'array',
        'closed_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Contexts\Identity\Domain\Models\User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Contexts\Identity\Domain\Models\User::class, 'assigned_to_user_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class, 'ticket_id')->orderBy('created_at');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public static function generateTicketNumber(): string
    {
        $prefix = 'TKT-' . date('Ymd') . '-';
        $last = static::where('ticket_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('ticket_number');

        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
