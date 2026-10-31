<?php

namespace App\Models;

use App\Services\WebsiteHtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\HtmlString;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'body', 'body_format', 'audience', 'publish_at', 'expires_at', 'status', 'created_by',
    ];

    /**
     * Existing and seeded notices are plain text unless the editor says
     * otherwise, so the model mirrors the database default.
     */
    protected $attributes = [
        'body_format' => 'text',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'notice_class', 'notice_id', 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function getAudienceLabelAttribute(): string
    {
        return match ($this->audience) {
            'all' => 'Everyone',
            'students' => 'Students',
            'staff' => 'Staff',
            'classes' => 'Selected Classes',
            default => ucfirst($this->audience),
        };
    }

    /**
     * The body as safe HTML. HTML notices are re-sanitised on output so even a
     * record edited directly in the database cannot inject active content;
     * plain text notices keep their line breaks and stay fully escaped.
     */
    public function getRenderedBodyAttribute(): HtmlString
    {
        if ($this->body_format === 'html') {
            return new HtmlString(app(WebsiteHtmlSanitizer::class)->sanitize($this->body));
        }

        return new HtmlString(nl2br(e((string) $this->body)));
    }

    /**
     * A single line, unformatted version of the body for lists and previews.
     */
    public function getPlainBodyAttribute(): string
    {
        $text = $this->body_format === 'html'
            ? strip_tags((string) $this->body)
            : (string) $this->body;

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
