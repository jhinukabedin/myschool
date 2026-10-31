<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteContentRevision extends Model
{
    protected $fillable = ['website_content_id', 'user_id', 'payload', 'reason'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(WebsiteContent::class, 'website_content_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
