@extends('layouts.app')

@section('title', $content->exists ? 'Edit Website Content' : 'New Website Content')

@php
    $type = old('type', $content->type);
    $typeLabel = $types[$type] ?? ucfirst(str_replace('_', ' ', $type));
    $formTitle = $section['title'] ?? $typeLabel;

    $fields = \App\Models\WebsiteContent::formFieldsFor($type);
    $publishFields = \App\Models\WebsiteContent::publishFieldsFor($type);

    $baseMeta = [
        'title' => ['label' => 'Title', 'required' => true, 'span' => 2],
        'subtitle' => ['label' => 'Sub title', 'span' => 2],
        'slug' => ['label' => 'Slug', 'help' => 'Leave blank to generate from the title.'],
        'excerpt' => ['label' => 'Short description', 'type' => 'textarea', 'rows' => 2, 'span' => 2],
        'body' => ['label' => 'Body', 'type' => 'richtext', 'span' => 2],
        'due_date' => ['label' => 'Deadline', 'type' => 'date'],
        'category' => ['label' => 'Category'],
        'link_url' => ['label' => 'Link', 'placeholder' => '/page or https://example.com'],
        'button_label' => ['label' => 'Button label'],
        'image' => ['label' => 'Image', 'type' => 'file', 'span' => 2, 'accept' => 'image/jpeg,image/png,image/webp,image/gif'],
        'image_title' => ['label' => 'Image title', 'help' => 'Small label shown on top of the image.'],
        'file' => ['label' => 'File', 'type' => 'file', 'span' => 2, 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv'],
        'template' => ['label' => 'Page template', 'type' => 'select', 'default' => 'default', 'options' => ['default' => 'Default', 'full-width' => 'Full width', 'sidebar' => 'With sidebar', 'landing' => 'Landing page']],
        'meta_title' => ['label' => 'SEO title', 'help' => 'Falls back to the title.'],
        'meta_description' => ['label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'span' => 2],
    ];

    // Some types need section-specific wording rather than the generic labels.
    $overrides = match ($type) {
        'slider' => [
            'category' => ['label' => 'Tagline (hero kicker)', 'placeholder' => 'e.g. Welcome', 'help' => 'Small label shown above the main title.'],
            'title' => ['label' => 'Main title (optional)', 'placeholder' => 'School slogan or headline', 'required' => false, 'help' => 'Leave blank to show only the background image.'],
            'excerpt' => ['label' => 'Short description', 'rows' => 3, 'help' => 'Shown under the title on the slide.'],
            'button_label' => ['label' => 'Button name', 'placeholder' => 'e.g. Apply now'],
            'link_url' => ['label' => 'Button link', 'placeholder' => '/admission/apply'],
            'image' => ['label' => 'Background image', 'help' => 'Best size: 1920 × 1080 px (16:9), under 4 MB. The image fills the whole slide (cover). If you add a title, a dark shadow is placed over the image for readability.'],
        ],
        'introduction' => [
            'title' => ['label' => 'Section title', 'help' => 'Shown as the homepage section heading and as the details page title.'],
            'subtitle' => ['label' => 'Sub title', 'help' => 'Heading on the homepage card and under the title on the details page.'],
            'excerpt' => ['label' => 'Short description', 'rows' => 4, 'help' => 'Short summary shown on the homepage card. The full body appears only on the details page.'],
            'image' => ['label' => 'Homepage image', 'help' => 'Image shown on the homepage card. Best size: 900 × 700 px, under 4 MB.'],
            'image_title' => ['label' => 'Image title', 'help' => 'Small label shown on top of the homepage image. Leave blank to use the school name.'],
            'body' => ['label' => 'Details page body', 'type' => 'richtext', 'span' => 2, 'help' => 'Full introduction shown on the details page below the page header.'],
            'button_label' => ['label' => 'Button label', 'placeholder' => 'আরও জানুন'],
            'link_url' => ['label' => 'Button link', 'placeholder' => 'Leave blank to open the পরিচিতি page'],
        ],
        'video' => [
            'link_url' => ['label' => 'YouTube URL', 'placeholder' => 'https://www.youtube.com/watch?v=xxxxxxxxxxx', 'help' => 'Paste a YouTube link. It is embedded on the public video gallery.'],
            'image' => ['label' => 'Thumbnail (optional)', 'help' => 'Shown on the video card. Leave blank to use YouTube\'s default thumbnail.'],
            'excerpt' => ['label' => 'Short description', 'rows' => 2],
        ],
        'routine' => [
            'category' => ['label' => 'Class / group', 'placeholder' => 'e.g. Class 6'],
            'file' => ['label' => 'Routine file', 'help' => 'PDF or image of the class routine.'],
            'excerpt' => ['label' => 'Notes', 'rows' => 2],
        ],
        'syllabus' => [
            'category' => ['label' => 'Class / subject', 'placeholder' => 'e.g. Class 9 — Science'],
            'file' => ['label' => 'Syllabus file', 'help' => 'PDF shown under Downloads.'],
            'excerpt' => ['label' => 'Notes', 'rows' => 2],
        ],
        'academic_calendar' => [
            'file' => ['label' => 'Calendar file (optional)', 'help' => 'Optional PDF attachment.'],
            'body' => ['label' => 'Details', 'type' => 'richtext', 'span' => 2],
        ],
        'assignment' => [
            'title' => ['label' => 'Assignment title', 'placeholder' => 'e.g. Chapter 5 practice problems'],
            'subtitle' => ['label' => 'Subject', 'placeholder' => 'e.g. Mathematics'],
            'category' => ['label' => 'Class / group', 'placeholder' => 'e.g. Class 9 (leave blank for all classes)'],
            'excerpt' => ['label' => 'Instructions', 'rows' => 3, 'help' => 'Short instructions shown in the student portal.'],
            'due_date' => ['label' => 'Deadline', 'type' => 'date'],
            'body' => ['label' => 'Details', 'type' => 'richtext', 'span' => 2, 'help' => 'Optional longer description or rubric.'],
            'file' => ['label' => 'Attachment (optional)', 'help' => 'Question paper, worksheet or reference file.'],
        ],
        'admission_info' => [
            'file' => ['label' => 'Attachment (optional)', 'help' => 'Optional PDF such as the admission notice.'],
            'link_url' => ['label' => 'Apply / details link', 'placeholder' => '/admission/apply'],
            'button_label' => ['label' => 'Button name'],
        ],
        'academic' => [
            'title' => ['label' => 'Card title', 'placeholder' => 'e.g. ক্লাস ও বিষয়'],
            'subtitle' => ['label' => 'Sub title', 'help' => 'Short line shown under the card title.'],
            'category' => ['label' => 'Icon', 'placeholder' => 'e.g. graduation-cap', 'help' => 'An icon name from the shared icon set (calendar, users, book-open, download …) or a short letter.'],
            'excerpt' => ['label' => 'Short description', 'rows' => 2, 'help' => 'Fallback text when no sub title is set.'],
            'link_url' => ['label' => 'Link', 'placeholder' => '/academics or https://...'],
            'image' => ['label' => 'Image (optional)', 'help' => 'Replaces the icon on the card.'],
        ],
        default => [],
    };

    $meta = fn (string $name) => array_merge(
        $baseMeta[$name] ?? ['label' => ucfirst(str_replace('_', ' ', $name))],
        $overrides[$name] ?? []
    );

    $primary = array_values(array_intersect(
        $fields,
        ['title', 'subtitle', 'slug', 'excerpt', 'category', 'link_url', 'button_label', 'image', 'image_title', 'file', 'body', 'due_date']
    ));
    $presentation = array_values(array_intersect($fields, \App\Models\WebsiteContent::PRESENTATION_FIELDS));
    $span = fn (array $f) => ($f['span'] ?? 1) === 2 ? 'span-2' : '';
@endphp

@section('content')
    <x-page-header eyebrow="Website content" :title="$content->exists ? 'Edit: '.$formTitle : 'New: '.$formTitle"
        :subtitle="$section['subtitle'] ?? 'Only the fields this section needs are shown. Published items appear on the public website immediately.'">
        <x-slot:actions>
            @if ($content->exists && $content->public_url && $content->status === 'published')
                <x-button :href="$content->public_url" variant="secondary" icon="external-link" target="_blank">Preview</x-button>
            @endif
            <x-button :href="route('website-content.index')" variant="secondary" icon="arrow-left">Content library</x-button>
        </x-slot:actions>
    </x-page-header>


    <form id="content-form" method="POST" action="{{ $content->exists ? route('website-content.update', $content) : route('website-content.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($content->exists) @method('PUT') @endif
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="stack">
            <div>
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">{{ $formTitle }}</h3>
                            <p class="card-sub">{{ $section['subtitle'] ?? 'Content shown on the public website.' }}</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            @foreach ($primary as $name)
                                @php $f = $meta($name); @endphp

                                @if (($f['type'] ?? 'text') === 'textarea')
                                    <div class="{{ $span($f) }}">
                                        <x-textarea :name="$name" :label="$f['label']" :rows="$f['rows'] ?? 3"
                                            :value="$content->{$name}" :placeholder="$f['placeholder'] ?? null" :help="$f['help'] ?? null" />
                                    </div>
                                @elseif (($f['type'] ?? 'text') === 'richtext')
                                    <div class="span-2">
                                        <x-rich-text :name="$name" :label="$f['label']" :value="$content->{$name}"
                                            upload-route="website-content.image" :help="$f['help'] ?? null" />
                                    </div>
                                @elseif (($f['type'] ?? 'text') === 'file')
                                    <div class="{{ $span($f) }}">
                                        <x-input :name="$name" :label="$f['label']" type="file" :accept="$f['accept'] ?? null" />

                                        @if ($name === 'image' && $content->image_path)
                                            <img src="{{ $content->image_url }}" alt="" style="max-width:100%;max-height:150px;border-radius:8px;margin-top:8px">
                                            <p class="cell-sub">Current: {{ $content->image_path }}</p>
                                        @elseif ($name === 'file' && $content->file_path)
                                            <p class="cell-sub">Current: {{ $content->file_path }}</p>
                                        @endif

                                        @if (! empty($f['help']))
                                            <span class="field-hint">{{ $f['help'] }}</span>
                                        @endif
                                    </div>
                                @elseif (($f['type'] ?? 'text') === 'select')
                                    <div class="{{ $span($f) }}">
                                        <x-select :name="$name" :label="$f['label']" :options="$f['options']"
                                            :selected="$content->{$name} ?? ($f['default'] ?? null)" />
                                    </div>
                                @elseif (($f['type'] ?? 'text') === 'date')
                                    <div class="{{ $span($f) }}">
                                        <x-input :name="$name" :label="$f['label']" type="date"
                                            :value="$content->{$name}?->format('Y-m-d')" :help="$f['help'] ?? null" />
                                    </div>
                                @else
                                    <div class="{{ $span($f) }}">
                                        <x-input :name="$name" :label="$f['label']" :value="$content->{$name}"
                                            :placeholder="$f['placeholder'] ?? null" :help="$f['help'] ?? null"
                                            :required="$f['required'] ?? false" />
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Publishing</h3>
                            <p class="card-sub">Visibility on the public website.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            @if (in_array('status', $publishFields, true))
                                <div class="span-2">
                                    <x-select name="status" label="Status" :options="['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']" :selected="$content->status" required />
                                </div>
                            @endif

                            @if (in_array('published_at', $publishFields, true))
                                <div class="span-2">
                                    <x-input name="published_at" label="Publish at" type="datetime-local" :value="$content->published_at?->format('Y-m-d\TH:i')" help="Leave blank to publish now when status is Published." />
                                </div>
                            @endif

                            @if (in_array('sort_order', $publishFields, true))
                                <div class="span-2">
                                    <x-input name="sort_order" label="Sort order" type="number" min="0" :value="$content->sort_order ?? 0" help="Lower numbers appear first." />
                                </div>
                            @endif

                            @if (in_array('is_featured', $publishFields, true))
                                <div class="span-2">
                                    <input type="hidden" name="is_featured" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $content->is_featured))>
                                        <span>Feature this item on the home page</span>
                                    </label>
                                </div>
                            @endif

                            @if (! empty($presentation))
                                <div class="span-2" style="border-top:1px solid var(--border);padding-top:14px;margin-top:4px">
                                    <strong style="display:block;font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">SEO &amp; template</strong>
                                </div>
                                @foreach ($presentation as $name)
                                    @php $f = $meta($name); @endphp
                                    @if (($f['type'] ?? 'text') === 'textarea')
                                        <div class="{{ $span($f) }}">
                                            <x-textarea :name="$name" :label="$f['label']" :rows="$f['rows'] ?? 3" :value="$content->{$name}" :help="$f['help'] ?? null" />
                                        </div>
                                    @elseif (($f['type'] ?? 'text') === 'select')
                                        <div class="{{ $span($f) }}">
                                            <x-select :name="$name" :label="$f['label']" :options="$f['options']" :selected="$content->{$name} ?? ($f['default'] ?? null)" />
                                        </div>
                                    @else
                                        <div class="{{ $span($f) }}">
                                            <x-input :name="$name" :label="$f['label']" :value="$content->{$name}" :help="$f['help'] ?? null" />
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="card-footer" style="display:flex;justify-content:flex-end;gap:8px">
                        <x-button :href="route('website-content.index')" variant="ghost">Cancel</x-button>
                        <x-button type="submit" variant="primary" icon="save">{{ $content->exists ? 'Save changes' : 'Create' }}</x-button>
                    </div>
                </div>

                @if ($content->exists)
                    <div class="card" style="margin-top:16px">
                        <div class="card-header"><div><h3 class="card-title">Record</h3><p class="card-sub">Audit and lifecycle</p></div></div>
                        <div class="card-body">
                            <p class="muted" style="font-size:12px;margin-top:0">
                                Created {{ $content->created_at?->format('d M Y, h:i A') }}
                                @if ($content->editor) · last edited by {{ $content->editor->name }} @endif
                                @if ($content->publisher) · published by {{ $content->publisher->name }} @endif
                            </p>
                            @if ($content->status !== 'archived')
                                <form method="POST" action="{{ route('website-content.archive', $content) }}">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm" type="submit">Archive content</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>

    @if ($content->exists)
        <div class="card" style="margin-top:18px">
            <div class="card-header"><div><h3 class="card-title">Revision history</h3><p class="card-sub">Every save keeps the version it replaced. Restoring also saves the current version first.</p></div></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>When</th><th>By</th><th>Reason</th><th>Status at the time</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse ($content->revisions as $revision)
                            <tr>
                                <td>{{ $revision->created_at?->format('d M Y, h:i A') }}</td>
                                <td>{{ $revision->user?->name ?? 'System' }}</td>
                                <td>{{ $revision->reason ?? '—' }}</td>
                                <td><x-badge color="muted">{{ ucfirst($revision->payload['status'] ?? 'unknown') }}</x-badge></td>
                                <td>
                                    @can('website.publish')
                                        <form method="POST" action="{{ route('website-content.revisions.restore', [$content, $revision]) }}" onsubmit="return confirm('Restore this revision? The current version will be saved first.')">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm" type="submit">Restore</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state">No revisions yet. They are created on the next save.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @can('website.manage')
            <div class="card" style="margin-top:18px;border-color:#f3c2c2">
                <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
                    <div><strong>Delete this content permanently</strong><p class="muted" style="margin:4px 0 0;font-size:12px">Revisions are deleted too. Shared media files are kept.</p></div>
                    <form method="POST" action="{{ route('website-content.destroy', $content) }}" onsubmit="return confirm('Permanently delete this content? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit">Delete permanently</button>
                    </form>
                </div>
            </div>
        @endcan
    @endif
@endsection
