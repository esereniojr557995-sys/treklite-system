<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // --- Role helpers used throughout controllers/views/middleware ---
    //
    // Org chart hierarchy (Chapter 1): Owner sits at the top and holds
    // primary authority; the Co-Owner is positioned one level below and
    // is authorized to act on the Owner's behalf when needed; Staff
    // independently run day-to-day operations at the Matina/Malita
    // branches. Owner and Co-Owner share the same *system* permissions
    // below (both need full access to fulfill "acting on the Owner's
    // behalf"), even though the Owner remains the final authority on the
    // business itself.

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isCoOwner(): bool
    {
        return $this->role === 'co_owner';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** Owner and Co-Owner both get full, all-branch access per Chapter 1. */
    public function hasFullAccess(): bool
    {
        return in_array($this->role, ['owner', 'co_owner']);
    }
}
