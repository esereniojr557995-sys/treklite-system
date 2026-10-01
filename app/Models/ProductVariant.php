<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'sku', 'size', 'color', 'price', 'quantity', 'low_stock_threshold'];

    protected $casts = ['price' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Variant price if set, otherwise the product's price. */
    public function getSellingPriceAttribute(): float
    {
        return (float) ($this->price ?? $this->product->price);
    }

    /** "40 / Black", "M", or "Standard" when there is no size or color. */
    public function getLabelAttribute(): string
    {
        return collect([$this->size, $this->color])->filter()->implode(' / ') ?: 'Standard';
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= $this->low_stock_threshold;
    }
}
