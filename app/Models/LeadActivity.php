<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'lead_id',
    'changed_by_user_id',
    'action',
    'from_user_id',
    'to_user_id',
    'occurred_at'
])]
class LeadActivity extends Model
{
    public $timestamps = false;
}


