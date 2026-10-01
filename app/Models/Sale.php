<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'user_id', 'customer_name', 'customer_address', 'customer_contact',
        'payment_method', 'payment_reference', 'payment_proof_path',
        'amount_tendered', 'change_amount', 'total_amount', 'sold_at',
    ];

    protected $casts = [
        'sold_at'         => 'datetime',
        'total_amount'    => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount'   => 'decimal:2',
    ];

    public const PAYMENT_LABELS = [
        'cash' => 'Cash', 'gcash' => 'GCash', 'paymaya' => 'PayMaya', 'card' => 'Card (tap-to-pay)',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function getPaymentLabelAttribute(): string
    {
        return self::PAYMENT_LABELS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    public function getPaymentProofUrlAttribute(): ?string
    {
        return $this->payment_proof_path ? Storage::disk('public')->url($this->payment_proof_path) : null;
    }
}
