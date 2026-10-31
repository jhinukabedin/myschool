<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebsiteContent extends Model
{
    use HasFactory;

    public const TYPES = [
        'page' => 'Page',
        'news' => 'News',
        'event' => 'Event',
        'gallery' => 'Gallery item',
        'download' => 'Download',
        'faculty' => 'Faculty profile',
        'slider' => 'Homepage slider',
        'introduction' => 'Institution introduction',
        'administration_message' => 'Administration message',
        'principal_message' => 'Principal message',
        'president_message' => 'President message',
        'academic' => 'Academic corner item',
        'admission_info' => 'Admission information',
        'routine' => 'Class routine',
        'academic_calendar' => 'Academic calendar entry',
        'syllabus' => 'Syllabus',
        'assignment' => 'Assignment',
        'video' => 'Video gallery item',
    ];

    /**
     * Sections that are expected to have one published record. Pages, slider
     * items, academic cards, news, gallery items and administration messages
     * remain repeatable.
     */
    public const SINGLETON_TYPES = [
        'introduction', 'principal_message', 'president_message',
    ];

    /**
     * Content fields shown on the create/edit form for each type. Keeping this
     * per type means an editor only sees what that section actually uses
     * instead of every column in the table.
     *
     * @var array<string, array<int, string>>
     */
    public const FORM_FIELDS = [
        'page' => ['title', 'slug', 'excerpt', 'body', 'image', 'template', 'meta_title', 'meta_description'],
        'news' => ['title', 'slug', 'excerpt', 'body', 'image', 'category', 'meta_title', 'meta_description'],
        'event' => ['title', 'slug', 'excerpt', 'body', 'image', 'category'],
        'gallery' => ['title', 'image', 'excerpt'],
        'download' => ['title', 'excerpt', 'file', 'category'],
        'faculty' => ['title', 'category', 'excerpt', 'body', 'image'],
        'slider' => ['category', 'title', 'excerpt', 'button_label', 'link_url', 'image'],
        'introduction' => ['title', 'subtitle', 'excerpt', 'body', 'image', 'image_title', 'button_label', 'link_url'],
        'administration_message' => ['title', 'category', 'excerpt', 'body', 'image'],
        'principal_message' => ['title', 'category', 'excerpt', 'body', 'image'],
        'president_message' => ['title', 'category', 'excerpt', 'body', 'image'],
        'academic' => ['title', 'subtitle', 'category', 'excerpt', 'link_url', 'image'],
        'admission_info' => ['title', 'excerpt', 'body', 'image', 'file', 'button_label', 'link_url'],
        'routine' => ['title', 'category', 'excerpt', 'file', 'body'],
        'academic_calendar' => ['title', 'excerpt', 'body', 'file', 'image'],
        'syllabus' => ['title', 'category', 'excerpt', 'file'],
        'assignment' => ['title', 'subtitle', 'category', 'excerpt', 'due_date', 'body', 'file'],
        'video' => ['title', 'link_url', 'image', 'excerpt'],
    ];

    /**
     * Publishing controls shown next to the content fields for each type.
     *
     * @var array<string, array<int, string>>
     */
    public const PUBLISH_FIELDS = [
        'page' => ['status', 'published_at'],
        'news' => ['status', 'published_at', 'is_featured'],
        'event' => ['status', 'published_at', 'is_featured'],
        'slider' => ['status', 'sort_order'],
        'gallery' => ['status', 'sort_order'],
        'download' => ['status', 'sort_order'],
        'faculty' => ['status', 'sort_order'],
        'academic' => ['status', 'sort_order'],
        'admission_info' => ['status', 'sort_order'],
        'routine' => ['status', 'sort_order'],
        'academic_calendar' => ['status', 'published_at', 'sort_order'],
        'syllabus' => ['status', 'sort_order'],
        'assignment' => ['status', 'published_at', 'sort_order'],
        'video' => ['status', 'sort_order'],
        'default' => ['status'],
    ];

    /**
     * Presentation / SEO fields that only apply to full pages and articles.
     *
     * @var array<int, string>
     */
    public const PRESENTATION_FIELDS = ['template', 'meta_title', 'meta_description'];

    /**
     * @return array<int, string>
     */
    public static function formFieldsFor(string $type): array
    {
        return self::FORM_FIELDS[$type] ?? ['title'];
    }

    /**
     * @return array<int, string>
     */
    public static function publishFieldsFor(string $type): array
    {
        return self::PUBLISH_FIELDS[$type] ?? self::PUBLISH_FIELDS['default'];
    }

    protected $fillable = [
        'type', 'title', 'subtitle', 'slug', 'excerpt', 'body', 'body_format', 'image_path', 'image_title', 'file_path',
        'link_url', 'button_label',
        'category', 'is_featured', 'sort_order', 'status', 'published_at', 'due_date', 'created_by',
        'template', 'meta_title', 'meta_description', 'og_image_path',
        'published_by', 'updated_by', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'due_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsiteContentRevision::class)->latest('created_at');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    /**
     * Content saved before the rich text editor was introduced remains plain
     * text and is escaped. Sanitized HTML is only rendered for html records.
     */
    public function getBodyHtmlAttribute(): string
    {
        if (($this->body_format ?? 'text') === 'html') {
            return (string) $this->body;
        }

        return nl2br(e((string) $this->body));
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->og_image_path ? asset('storage/'.$this->og_image_path) : $this->image_url;
    }

    public function getPublicUrlAttribute(): ?string
    {
        return match ($this->type) {
            'news' => route('website.news.item', $this->slug),
            'page' => route('website.page', $this->slug),
            default => null,
        };
    }

    /**
     * Store the version that existed before an update. Revisions are immutable.
     */
    public function captureRevision(?int $userId = null, ?string $reason = null): WebsiteContentRevision
    {
        return $this->revisions()->create([
            'user_id' => $userId,
            'reason' => $reason,
            'payload' => $this->only([
                'type', 'title', 'subtitle', 'slug', 'excerpt', 'body', 'body_format', 'image_path', 'image_title', 'file_path',
                'link_url', 'button_label',
                'category', 'is_featured', 'sort_order', 'status', 'published_at', 'due_date',
                'template', 'meta_title', 'meta_description', 'og_image_path',
            ]),
        ]);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'item';
        $slug = $base;
        $i = 2;

        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
