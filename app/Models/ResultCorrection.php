<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultCorrection extends Model
{
    protected $fillable = [
        'mark_id', 'old_marks', 'new_marks', 'old_grade', 'new_grade',
        'old_status', 'new_status', 'reason', 'corrected_by',
    ];

    protected function casts(): array
    {
        return [
            'old_marks' => 'decimal:2',
            'new_marks' => 'decimal:2',
        ];
    }

    public function mark(): BelongsTo
    {
        return $this->belongsTo(Mark::class);
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
