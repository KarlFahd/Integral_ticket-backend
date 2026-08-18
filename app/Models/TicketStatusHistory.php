<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'old_status_id',
        'new_status_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Status, $this> */
    public function oldStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'old_status_id');
    }

    /** @return BelongsTo<Status, $this> */
    public function newStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'new_status_id');
    }
}
