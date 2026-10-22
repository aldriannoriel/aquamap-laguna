<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportEmailVerification extends Model
{
    protected $fillable = [
        'email_hash',
        'session_hash',
        'otp_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'consumed_at',
    ];

    protected $hidden = [
        'email_hash',
        'session_hash',
        'otp_hash',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
