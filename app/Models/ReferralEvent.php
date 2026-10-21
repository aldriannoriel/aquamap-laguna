<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
