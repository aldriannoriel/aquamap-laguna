<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportEvidence extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'report_evidence';

    protected $hidden = ['path', 'sha256'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(ReportReview::class, 'report_review_id');
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    protected function casts(): array
    {
        return ['byte_size' => 'integer'];
    }
}
