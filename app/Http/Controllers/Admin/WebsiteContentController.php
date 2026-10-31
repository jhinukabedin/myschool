<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\SchoolSetting;
use App\Models\WebsiteContent;
use App\Models\WebsiteContentRevision;
use App\Services\AuditLogService;
use App\Services\RichTextImageService;
use App\Services\WebsiteHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteContentController extends Controller
{
    /**
     * Dedicated homepage/content functions shown in the website administration
     * area. Each URL maps to one section but shares one hardened CRUD flow.
     */
    public const SECTIONS = [
        'slider' => ['type' => 'slider', 'title' => 'Homepage Slider', 'subtitle' => 'Slides, images, headings and call-to-action buttons'],
        'introduction' => ['type' => 'introduction', 'title' => 'Institution Introduction', 'subtitle' => 'The main introduction block on the homepage and About page'],
        'administration-message' => ['type' => 'administration_message', 'title' => 'Administration Message', 'subtitle' => 'Message from the school administration'],
        'principal-message' => ['type' => 'principal_message', 'title' => 'Principal Message', 'subtitle' => 'Principal portrait and message'],
        'president-message' => ['type' => 'president_message', 'title' => 'President Message', 'subtitle' => 'Governing body president portrait and message'],
        'academic-corner' => ['type' => 'academic', 'title' => 'Academic Corner', 'subtitle' => 'Cards linking to classes, faculty, calendar and downloads'],
        'news' => ['type' => 'news', 'title' => 'News', 'subtitle' => 'Published news and announcements'],
        'events' => ['type' => 'event', 'title' => 'Events', 'subtitle' => 'Upcoming and recent school events'],
        'gallery' => ['type' => 'gallery', 'title' => 'Photo Gallery', 'subtitle' => 'Gallery images and captions'],
        'pages' => ['type' => 'page', 'title' => 'Pages', 'subtitle' => 'About, admission, privacy and other CMS pages'],
        'downloads' => ['type' => 'download', 'title' => 'Downloads', 'subtitle' => 'Syllabus, forms and public files'],
        'faculty' => ['type' => 'faculty', 'title' => 'Faculty Profiles', 'subtitle' => 'Public teacher profiles'],
        'admission-info' => ['type' => 'admission_info', 'title' => 'Admission Info', 'subtitle' => 'Admission notice, eligibility and required documents'],
        'class-routine' => ['type' => 'routine', 'title' => 'Class Routine', 'subtitle' => 'Class-wise routine attachments and notes'],
        'academic-calendar' => ['type' => 'academic_calendar', 'title' => 'Academic Calendar', 'subtitle' => 'Exam and term dates published on the calendar page'],
        'syllabus' => ['type' => 'syllabus', 'title' => 'Syllabus', 'subtitle' => 'Class-wise syllabus files shown under Downloads'],
        'assignments' => ['type' => 'assignment', 'title' => 'Assignments', 'subtitle' => 'Homework, deadlines and files for students'],
        'video-gallery' => ['type' => 'video', 'title' => 'Video Gallery', 'subtitle' => 'YouTube videos with thumbnails'],
    ];

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly WebsiteHtmlSanitizer $sanitizer
    ) {}

    public function index(Request $request): View
    {
        return $this->renderIndex($request);
    }

    public function section(Request $request, string $section): View
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        $request->merge(['type' => self::SECTIONS[$section]['type']]);

        return $this->renderIndex($request, $section);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $type = (string) $request->input('type', 'news');
        abort_unless(array_key_exists($type, WebsiteContent::TYPES), 404);

        if (in_array($type, WebsiteContent::SINGLETON_TYPES, true)) {
            $existing = WebsiteContent::query()->ofType($type)->latest('id')->first();

            if ($existing) {
                return redirect()
                    ->route('website-content.edit', $existing)
                    ->with('success', 'This section already has content. Edit the existing record below.');
            }
        }

        return view('admin.website.form', [
            'content' => new WebsiteContent([
                'type' => $type,
                'status' => 'draft',
                'is_featured' => false,
                'sort_order' => WebsiteContent::query()->ofType($type)->max('sort_order') + 1,
            ]),
            'types' => WebsiteContent::TYPES,
            'section' => collect(self::SECTIONS)->firstWhere('type', $type),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (in_array($data['type'], WebsiteContent::SINGLETON_TYPES, true)) {
            $existing = WebsiteContent::query()->ofType($data['type'])->latest('id')->first();

            if ($existing) {
                return redirect()
                    ->route('website-content.edit', $existing)
                    ->with('success', 'This section is a single content block. The existing record is ready to edit.');
            }
        }

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['published_by'] = ($data['status'] ?? null) === 'published' ? $request->user()->id : null;
        $data['archived_at'] = ($data['status'] ?? null) === 'archived' ? now() : null;
        $data = $this->handleUploads($request, $data);

        $content = WebsiteContent::create($data);

        $this->audit->record('website_content_created', 'Website', "Created {$content->type}: {$content->title}", $content, null, $content->only(['type', 'title', 'slug', 'status']));

        return redirect()->route('website-content.index')->with('success', 'Website content created.');
    }

    public function edit(WebsiteContent $content): View
    {
        return view('admin.website.form', [
            'content' => $content->load(['revisions.user']),
            'types' => WebsiteContent::TYPES,
            'section' => collect(self::SECTIONS)->firstWhere('type', $content->type),
        ]);
    }

    public function update(Request $request, WebsiteContent $content): RedirectResponse
    {
        $data = $this->validated($request, $content);
        $data = $this->handleUploads($request, $data, $content);
        $data['updated_by'] = $request->user()->id;

        if (($data['status'] ?? null) === 'published') {
            $data['published_by'] = $request->user()->id;
            $data['archived_at'] = null;
        } elseif (($data['status'] ?? null) === 'archived') {
            $data['archived_at'] = now();
        }

        DB::transaction(function () use ($content, $data, $request) {
            $content->captureRevision($request->user()->id, 'Before edit');
            $before = $content->only(['type', 'title', 'slug', 'status', 'published_at']);

            $content->update($data);

            $this->audit->record(
                'website_content_updated', 'Website',
                "Updated {$content->type}: {$content->title}", $content,
                $before, $content->only(['type', 'title', 'slug', 'status', 'published_at'])
            );
        });

        return redirect()->route('website-content.edit', $content)->with('success', 'Website content updated. The previous version was saved in revisions.');
    }

    /**
     * Accept an inline image from the rich text editor and return its public
     * URL so it can be embedded without leaving the form.
     */
    public function uploadImage(Request $request, RichTextImageService $images): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $stored = $images->store($request->file('image'), $request->user()->id);

        return response()->json(['url' => $stored['url']]);
    }

    public function archive(WebsiteContent $content): RedirectResponse
    {
        $before = $content->status;
        $content->captureRevision(request()->user()?->id, 'Before archive');
        $content->update(['status' => 'archived', 'archived_at' => now()]);

        $this->audit->record('website_content_archived', 'Website', "Archived {$content->type}: {$content->title}", $content, ['status' => $before], ['status' => 'archived']);

        return back()->with('success', 'Content archived. It is hidden from the public site but retained in the admin list.');
    }

    public function toggleStatus(WebsiteContent $content): RedirectResponse
    {
        $before = $content->status;
        $publishing = $content->status !== 'published';

        $content->update([
            'status' => $publishing ? 'published' : 'draft',
            'published_at' => $publishing ? ($content->published_at ?? now()) : $content->published_at,
            'published_by' => $publishing ? request()->user()?->id : $content->published_by,
            'archived_at' => null,
            'updated_by' => request()->user()?->id,
        ]);

        $this->audit->record(
            'website_content_status_toggled', 'Website',
            "{$content->type} {$content->title} ".($publishing ? 'enabled' : 'disabled'),
            $content, ['status' => $before], ['status' => $content->status]
        );

        return back()->with('success', $publishing
            ? 'Item enabled and visible on the public site.'
            : 'Item disabled and hidden from the public site.');
    }

    public function restoreRevision(Request $request, WebsiteContent $content, WebsiteContentRevision $revision): RedirectResponse
    {
        abort_unless($revision->website_content_id === $content->id, 404);

        DB::transaction(function () use ($content, $revision, $request) {
            $content->captureRevision($request->user()->id, 'Before restore');
            $payload = collect($revision->payload)
                ->only([
                    'type', 'title', 'subtitle', 'slug', 'excerpt', 'body', 'body_format', 'image_path', 'image_title', 'file_path',
                    'link_url', 'button_label',
                    'category', 'is_featured', 'sort_order', 'status', 'published_at', 'due_date',
                    'template', 'meta_title', 'meta_description', 'og_image_path',
                ])
                ->all();
            $payload['updated_by'] = $request->user()->id;
            $payload['archived_at'] = ($payload['status'] ?? null) === 'archived' ? now() : null;
            if (($payload['status'] ?? null) === 'published') {
                $payload['published_by'] = $request->user()->id;
            }

            $content->update($payload);

            $this->audit->record(
                'website_revision_restored', 'Website',
                "Restored revision #{$revision->id} of {$content->type}: {$content->title}",
                $content, null, ['revision_id' => $revision->id]
            );
        });

        return redirect()->route('website-content.edit', $content)->with('success', 'Revision restored. The current version was saved first so the change can be reversed.');
    }

    public function destroy(WebsiteContent $content): RedirectResponse
    {
        $payload = $content->only(['type', 'title', 'slug', 'status']);
        $imagePath = $content->image_path;
        $ogImagePath = $content->og_image_path;
        $filePath = $content->file_path;

        DB::transaction(function () use ($content) {
            $content->delete();
        });

        $this->deleteUnusedFiles([$imagePath, $ogImagePath, $filePath]);
        $this->audit->record('website_content_deleted', 'Website', "Deleted {$payload['type']}: {$payload['title']}", null, $payload, null);

        return redirect()->route('website-content.index')->with('success', 'Content deleted. Unused uploaded files were removed; shared files were kept.');
    }

    private function renderIndex(Request $request, ?string $sectionKey = null): View
    {
        $items = WebsiteContent::query()
            ->with('creator')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.website.index', [
            'items' => $items,
            'types' => WebsiteContent::TYPES,
            'sections' => self::SECTIONS,
            'activeSection' => $sectionKey,
            'section' => $sectionKey ? self::SECTIONS[$sectionKey] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WebsiteContent $content = null): array
    {
        // A hero slide may be image-only, so its title is optional. Every other
        // content type still requires a title.
        $titleRequired = $request->input('type') !== 'slider';

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(WebsiteContent::TYPES))],
            'title' => [$titleRequired ? 'required' : 'nullable', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'image_title' => ['nullable', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('website_contents', 'slug')->ignore($content?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:50000'],
            'body_format' => ['nullable', Rule::in(['text', 'html'])],
            'link_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', (string) $value)) {
                    $fail('The link must be a relative path or an http(s), mailto or tel URL.');
                }
            }],
            'button_label' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'published_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'template' => ['nullable', Rule::in(['default', 'full-width', 'sidebar', 'landing'])],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'og_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt,csv', 'max:10240'],
        ]);

        unset($data['image'], $data['file'], $data['og_image']);

        // Only touch fields the type actually exposes. Anything the focused
        // form does not submit keeps its stored value on update.
        if ($request->has('is_featured')) {
            $data['is_featured'] = $request->boolean('is_featured');
        }

        if (array_key_exists('sort_order', $data)) {
            $data['sort_order'] = (int) $data['sort_order'];
        }

        if (array_key_exists('body', $data) || array_key_exists('body_format', $data)) {
            $data['body_format'] = $data['body_format'] ?? 'text';
            $data['body'] = $data['body_format'] === 'html'
                ? $this->sanitizer->sanitize($data['body'] ?? '')
                : ($data['body'] ?? null);
        }

        $data['slug'] = ($data['slug'] ?? null) ?: WebsiteContent::uniqueSlug((string) ($data['title'] ?? ''), $content?->id);
        $data['title'] = (string) ($data['title'] ?? '');

        if ($data['status'] === 'published' && empty($data['published_at'] ?? null) && empty($content?->published_at)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleUploads(Request $request, array $data, ?WebsiteContent $content = null): array
    {
        if ($request->hasFile('image')) {
            if ($content?->image_path) {
                Storage::disk('public')->delete($content->image_path);
            }
            $data['image_path'] = $request->file('image')->store('website/images', 'public');
        }

        if ($request->hasFile('file')) {
            if ($content?->file_path) {
                Storage::disk('public')->delete($content->file_path);
            }
            $data['file_path'] = $request->file('file')->store('website/files', 'public');
        }

        if ($request->hasFile('og_image')) {
            if ($content?->og_image_path) {
                Storage::disk('public')->delete($content->og_image_path);
            }
            $data['og_image_path'] = $request->file('og_image')->store('website/social', 'public');
        }

        return $data;
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    private function deleteUnusedFiles(array $paths): void
    {
        $settings = SchoolSetting::query()
            ->whereIn('key', ['website_logo_path', 'website_logo_mark', 'school_logo_path'])
            ->pluck('value')
            ->filter()
            ->all();

        foreach (array_unique(array_filter($paths)) as $path) {
            $usedByContent = WebsiteContent::query()
                ->where(fn ($query) => $query->where('image_path', $path)
                    ->orWhere('og_image_path', $path)
                    ->orWhere('file_path', $path))
                ->exists();
            $usedByMedia = Media::query()->where('path', $path)->exists();
            $usedBySetting = in_array($path, $settings, true);

            if (! $usedByContent && ! $usedByMedia && ! $usedBySetting) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
