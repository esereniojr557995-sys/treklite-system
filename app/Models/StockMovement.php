<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['variant_id', 'user_id', 'type', 'quantity_before', 'quantity_change', 'quantity_after', 'note'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'receive' ? 'Received' : 'Counted';
    }

    /** Short trace such as "Received +10 (5 → 15)" or "Counted 8 (system 10, −2)". */
    public function getSummaryAttribute(): string
    {
        $before = $this->quantity_before ?? ($this->quantity_after - $this->quantity_change);

        if ($this->type === 'receive') {
            return 'Received +' . $this->quantity_change . ' (' . $before . ' → ' . $this->quantity_after . ')';
        }

        $diff = $this->quantity_change === 0 ? 'matched' : ($this->quantity_change > 0 ? '+' : '−') . abs($this->quantity_change);

        return 'Counted ' . $this->quantity_after . ' (system ' . $before . ', ' . $diff . ')';
    }
}
