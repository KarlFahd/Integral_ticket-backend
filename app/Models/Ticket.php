<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category_id',
        'priority_id',
        'status_id',
        'attachment',
        'created_by',
    ];

    /** @return HasMany<TicketStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Priority, $this> */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    /** @return BelongsTo<Status, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
