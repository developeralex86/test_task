<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model {
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'company',
        'status', 'assigned_user_id', 'notes',
    ];

    // The fix: defines the relationship for eager loading
    public function assignedUser(): BelongsTo {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function activities(): HasMany {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    // Query scopes
    public function scopeOpen($query) {
        return $query->where('status', 'open');
    }
    public function scopeSearch($query, string $term) {
        return $query->where(fn($q) => $q
            ->orWhere('name', 'like', "%{$term}%")
            ->orWhere('company', 'like', "%{$term}%")
        );
    }
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function latestNote(): HasOne
    {
        return $this->hasOne(LeadNote::class)->latestOfMany();
    }
}
