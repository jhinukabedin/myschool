@extends('layouts.app')

@section('title', 'Media Library')

@push('head')
    <style>
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; padding: 16px; }
        .media-card { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
        .media-thumb { aspect-ratio: 4 / 3; background: #f1f5f9; display: grid; place-items: center; overflow: hidden; border: 0; padding: 0; width: 100%; cursor: zoom-in; }
        .media-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .media-file { width: 54px; height: 64px; border-radius: 8px; background: #e8eef7; color: #385d79; display: grid; place-items: center; font-weight: 800; font-size: 11px; text-transform: uppercase; }
        .media-info { padding: 10px 12px; display: grid; gap: 3px; }
        .media-info strong { font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .media-info small { color: #667085; font-size: 11px; }
        .media-actions { display: flex; gap: 5px; flex-wrap: wrap; padding: 0 12px 12px; margin-top: auto; }
        .media-actions .btn { font-size: 11px; padding: 4px 7px; }
        .media-meta { border-top: 1px solid var(--border); padding: 8px 12px; font-size: 11px; }
        .media-meta summary { cursor: pointer; color: #667085; }
        .media-meta form { display: grid; gap: 6px; margin-top: 8px; }
        .media-meta input { width: 100%; font-size: 12px; padding: 6px 8px; }
        dialog.lightbox { border: 0; padding: 0; background: transparent; max-width: min(1100px, 94vw); max-height: 92vh; }
        dialog.lightbox::backdrop { background: rgba(5, 12, 24, .82); }
        .lightbox-inner { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 30px 80px rgba(0,0,0,.35); }
        .lightbox-inner img { display: block; max-width: 100%; max-height: 78vh; margin: 0 auto; background: #0b1220; }
        .lightbox-bar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 14px; font-size: 12px; }
        .lightbox-bar button { border: 0; background: var(--danger, #b42318); color: #fff; border-radius: 7px; padding: 6px 10px; cursor: pointer; }
    </style>
@endpush

@section('content')
    <x-page-header eyebrow="Website" title="Media Library" subtitle="Upload, preview and safely remove images and documents used by the public website.">
        <x-slot:actions>
            <x-button :href="route('website-content.index')" variant="secondary" icon="layout-dashboard">Content library</x-button>
        </x-slot:actions>
    </x-page-header>


    <div class="grid-2">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Upload media</h3><p class="card-sub">Up to 20 files at once, 10 MB each. Images, PDFs, Office documents and archives.</p></div></div>
            <form method="POST" action="{{ route('media.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-section">
                    <div class="form-grid">
                        <div class="span-2"><x-input name="files[]" label="Files" type="file" multiple required accept="image/jpeg,image/png,image/webp,image/gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv" /></div>
                        <x-input name="category" label="Category" placeholder="Gallery, Notice, Academic" />
                        <x-input name="alt_text" label="Default alt text" placeholder="Describe the image for accessibility" />
                    </div>
                </div>
                <div class="card-footer" style="display:flex;justify-content:flex-end">
                    <x-button type="submit" variant="primary" icon="upload">Upload files</x-button>
                </div>
            </form>
        </div>
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Storage safety</h3><p class="card-sub">Deletion rules</p></div></div>
            <div class="card-body">
                <div class="warning-box" style="margin-bottom:12px">A file used by website content or site settings cannot be deleted from the media library. Replace the reference first.</div>
                <p class="muted" style="font-size:12px;margin:0">Test the public page after replacing a file. The library tracks disk path, MIME type, dimensions and uploader for every file.</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-header">
            <div><h3 class="card-title">Files</h3><p class="card-sub">{{ $media->total() }} file(s)</p></div>
            <form method="GET" action="{{ route('media.index') }}" class="filter-bar" style="margin:0;flex-wrap:wrap">
                <div class="field"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search name or alt text"></div>
                <div class="field">
                    <select class="select" name="kind">
                        <option value="">Any kind</option>
                        <option value="image" @selected(request('kind') === 'image')>Images</option>
                        <option value="application" @selected(request('kind') === 'application')>Documents</option>
                    </select>
                </div>
                <div class="field">
                    <select class="select" name="category">
                        <option value="">Any category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" icon="filter">Filter</x-button>
            </form>
        </div>

        <div class="media-grid">
            @forelse ($media as $file)
                <article class="media-card">
                    @if ($file->isImage())
                        <button type="button" class="media-thumb" data-media-preview data-url="{{ $file->url }}" data-title="{{ $file->original_name }}" data-caption="{{ $file->caption }}">
                            <img src="{{ $file->url }}" alt="{{ $file->alt_text ?: $file->original_name }}" loading="lazy">
                        </button>
                    @else
                        <div class="media-thumb" style="cursor:default"><span class="media-file">{{ $file->extension ?: 'FILE' }}</span></div>
                    @endif
                    <div class="media-info">
                        <strong title="{{ $file->original_name }}">{{ $file->original_name }}</strong>
                        <small>{{ $file->human_size }}@if ($file->width) · {{ $file->width }}×{{ $file->height }}@endif</small>
                        <small>{{ $file->category ?: 'Uncategorised' }} · {{ $file->created_at?->format('d M Y') }}</small>
                    </div>
                    <div class="media-actions">
                        <a class="btn btn-secondary" href="{{ $file->url }}" target="_blank" rel="noopener">Open</a>
                        <button type="button" class="btn btn-secondary" data-copy="{{ $file->path }}">Copy path</button>
                        <form method="POST" action="{{ route('media.destroy', $file) }}" onsubmit="return confirm('Delete this file? It will fail safely if content still references it.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger" type="submit">Delete</button>
                        </form>
                    </div>
                    <details class="media-meta">
                        <summary>Edit metadata</summary>
                        <form method="POST" action="{{ route('media.update', $file) }}">
                            @csrf
                            @method('PUT')
                            <input name="alt_text" value="{{ $file->alt_text }}" placeholder="Alt text" maxlength="255">
                            <input name="caption" value="{{ $file->caption }}" placeholder="Caption" maxlength="500">
                            <input name="category" value="{{ $file->category }}" placeholder="Category" maxlength="80">
                            <button class="btn btn-secondary btn-sm" type="submit">Save</button>
                        </form>
                    </details>
                </article>
            @empty
                <div class="empty-state" style="grid-column:1/-1">No media files have been uploaded.</div>
            @endforelse
        </div>
        <div class="card-footer">{{ $media->links() }}</div>
    </div>

    <dialog class="lightbox" id="media-lightbox">
        <div class="lightbox-inner">
            <img id="lightbox-image" src="" alt="">
            <div class="lightbox-bar">
                <span id="lightbox-caption"></span>
                <button type="button" onclick="document.getElementById('media-lightbox').close()">Close</button>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        (() => {
            const dialog = document.getElementById('media-lightbox');
            const image = document.getElementById('lightbox-image');
            const caption = document.getElementById('lightbox-caption');

            document.querySelectorAll('[data-media-preview]').forEach((button) => {
                button.addEventListener('click', () => {
                    image.src = button.dataset.url;
                    image.alt = button.dataset.title || '';
                    caption.textContent = [button.dataset.title, button.dataset.caption].filter(Boolean).join(' · ');
                    dialog.showModal();
                });
            });

            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });

            document.querySelectorAll('[data-copy]').forEach((button) => {
                button.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(button.dataset.copy);
                        const label = button.textContent;
                        button.textContent = 'Copied';
                        setTimeout(() => { button.textContent = label; }, 1200);
                    } catch (error) {
                        window.prompt('Copy this media path', button.dataset.copy);
                    }
                });
            });
        })();
    </script>
@endpush
