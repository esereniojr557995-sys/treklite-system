<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = ['name', 'category', 'brand', 'description', 'image_path', 'price'];

    protected $casts = ['price' => 'decimal:2'];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->variants->sum('quantity');
    }
}
