<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadNote extends Model
{
    use HasFactory;

    public $timestamps = false;        // ← must have this
    const CREATED_AT = 'created_at';   // ← and this

    protected $fillable = ['lead_id', 'user_id', 'note', 'created_at'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}