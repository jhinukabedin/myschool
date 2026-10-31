@extends('layouts.public')

@section('title', 'গ্যালারি')

@push('head')
    <style>
        .gallery-item { position: relative; border: 0; padding: 0; width: 100%; cursor: zoom-in; overflow: hidden; }
        .gallery-item img { transition: transform .3s ease; }
        .gallery-item:hover img { transform: scale(1.04); }
        dialog.gallery-lightbox { border: 0; padding: 0; background: transparent; max-width: min(1100px, 95vw); max-height: 94vh; }
        dialog.gallery-lightbox::backdrop { background: rgba(5, 12, 24, .86); }
        .gallery-shell { background: #0c1424; border-radius: 14px; overflow: hidden; box-shadow: 0 30px 90px rgba(0,0,0,.5); }
        .gallery-shell img { display: block; max-width: 100%; max-height: 80vh; margin: 0 auto; }
        .gallery-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; color: #e6edf7; font-size: 13px; }
        .gallery-bar .actions { display: flex; gap: 8px; }
        .gallery-bar button { border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.1); color: #fff; border-radius: 8px; padding: 6px 10px; cursor: pointer; }
    </style>
@endpush

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / গ্যালারি</div><h1>ফটো গ্যালারি</h1><p>বিদ্যালয়ের স্মরণীয় মুহূর্ত</p></div></section>

    <section class="section"><div class="container">
        <div class="gallery-grid" style="grid-template-columns:repeat(4,1fr);padding:0">
            @forelse ($items as $item)
                <figure style="margin:0">
                    @if ($item->image_url)
                        <button type="button" class="gallery-item" data-gallery-image="{{ $item->image_url }}" data-gallery-title="{{ $item->title }}" data-gallery-caption="{{ $item->excerpt }}">
                            <img src="{{ $item->image_url }}" alt="{{ $item->title }}" style="width:100%;height:100%;object-fit:cover">
                            <span>{{ $item->title }}</span>
                        </button>
                    @else
                        <div class="gallery-item" style="cursor:default"><span>{{ $item->title }}</span></div>
                    @endif
                    @if ($item->excerpt)<figcaption class="muted" style="font-size:13px;margin-top:6px">{{ $item->excerpt }}</figcaption>@endif
                </figure>
            @empty
                <div class="card article" style="grid-column:1/-1"><p>গ্যালারিতে এখনো কোনো ছবি প্রকাশ করা হয়নি।</p></div>
            @endforelse
        </div>
        <div style="margin-top:16px">{{ $items->links() }}</div>
    </div></section>

    <dialog class="gallery-lightbox" id="gallery-lightbox" aria-label="Gallery image preview">
        <div class="gallery-shell">
            <img id="gallery-lightbox-image" src="" alt="">
            <div class="gallery-bar">
                <span id="gallery-lightbox-caption"></span>
                <div class="actions">
                    <button type="button" data-gallery-prev aria-label="Previous image">←</button>
                    <button type="button" data-gallery-next aria-label="Next image">→</button>
                    <button type="button" data-gallery-close>বন্ধ করুন</button>
                </div>
            </div>
        </div>
    </dialog>
@endsection

@push('scripts')
    <script>
        (() => {
            const items = Array.from(document.querySelectorAll('[data-gallery-image]'));
            const dialog = document.getElementById('gallery-lightbox');
            const image = document.getElementById('gallery-lightbox-image');
            const caption = document.getElementById('gallery-lightbox-caption');
            if (!items.length || !dialog || !image || !caption) return;

            let active = 0;
            const show = (index) => {
                active = (index + items.length) % items.length;
                const item = items[active];
                image.src = item.dataset.galleryImage;
                image.alt = item.dataset.galleryTitle || '';
                caption.textContent = [item.dataset.galleryTitle, item.dataset.galleryCaption].filter(Boolean).join(' · ');
                if (!dialog.open) dialog.showModal();
            };

            items.forEach((item, index) => item.addEventListener('click', () => show(index)));
            document.querySelector('[data-gallery-prev]')?.addEventListener('click', () => show(active - 1));
            document.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(active + 1));
            document.querySelector('[data-gallery-close]')?.addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
            dialog.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft') show(active - 1);
                if (event.key === 'ArrowRight') show(active + 1);
            });
        })();
    </script>
@endpush
