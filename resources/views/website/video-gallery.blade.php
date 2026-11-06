@extends('layouts.public')

@section('title', 'ভিডিও গ্যালারি')

@push('head')
    <style>
        .video-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .video-card { border: 1px solid var(--line, #e5e8ee); border-radius: 12px; overflow: hidden; background: #fff; text-align: left; padding: 0; cursor: pointer; display: flex; flex-direction: column; }
        .video-card .thumb { position: relative; aspect-ratio: 16 / 9; background: #0b1a2b; overflow: hidden; }
        .video-card .thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .3s ease; }
        .video-card:hover .thumb img { transform: scale(1.05); }
        .video-card .play { position: absolute; inset: 0; display: grid; place-items: center; }
        .video-card .play span { width: 54px; height: 54px; border-radius: 50%; background: rgba(224, 30, 30, .92); color: #fff; display: grid; place-items: center; font-size: 20px; padding-left: 4px; box-shadow: 0 10px 30px rgba(0,0,0,.35); }
        .video-card .meta { padding: 12px 14px; }
        .video-card .meta strong { display: block; color: var(--navy, #12263f); }
        .video-card .meta p { margin: 4px 0 0; font-size: 13px; color: #6e7988; }
        dialog.video-lightbox { border: 0; padding: 0; background: transparent; width: min(940px, 94vw); }
        dialog.video-lightbox::backdrop { background: rgba(5, 12, 24, .86); }
        .video-shell { background: #0c1424; border-radius: 14px; overflow: hidden; box-shadow: 0 30px 90px rgba(0,0,0,.5); }
        .video-frame { position: relative; aspect-ratio: 16 / 9; }
        .video-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .video-bar { display: flex; justify-content: flex-end; padding: 10px 12px; }
        .video-bar button { border: 1px solid rgba(255,255,255,.3); background: rgba(255,255,255,.1); color: #fff; border-radius: 8px; padding: 6px 12px; cursor: pointer; }
        @media (max-width: 900px) { .video-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .video-grid { grid-template-columns: 1fr; } }
    </style>
@endpush

@php
    $embedSrc = function (?string $url): ?string {
        if (! $url) {
            return null;
        }
        if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }
        if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }
        return null;
    };
    $defaultThumb = fn (?string $url) => ($id = $url && preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})#', $url, $m) ? $m[1] : null)
        ? 'https://i.ytimg.com/vi/'.$m[1].'/hqdefault.jpg'
        : null;
@endphp

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / মিডিয়া / ভিডিও গ্যালারি</div><h1>ভিডিও গ্যালারি</h1><p>বিদ্যালয়ের অনুষ্ঠান ও কার্যক্রমের ভিডিও</p></div></section>

    <section class="section"><div class="container">
        @if ($items->isEmpty())
            <div class="card article"><p>বর্তমানে কোনো ভিডিও প্রকাশ করা হয়নি।</p></div>
        @else
            <div class="video-grid">
                @foreach ($items as $item)
                    @php
                        $thumb = $item->image_url ?: $defaultThumb($item->link_url);
                        $embed = $embedSrc($item->link_url);
                    @endphp
                    <button type="button" class="video-card"
                        data-video-embed="{{ $embed }}"
                        data-video-url="{{ $item->link_url }}"
                        data-video-title="{{ $item->title }}">
                        <span class="thumb">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="{{ $item->title }}" loading="lazy">
                            @else
                                <span style="display:grid;place-items:center;height:100%;color:#8fb0d1;font-size:13px">ভিডিও</span>
                            @endif
                            <span class="play"><span>▶</span></span>
                        </span>
                        <span class="meta">
                            <strong>{{ $item->title }}</strong>
                            @if ($item->excerpt)<p>{{ $item->excerpt }}</p>@endif
                        </span>
                    </button>
                @endforeach
            </div>
        @endif
    </div></section>

    <dialog class="video-lightbox" id="video-lightbox">
        <div class="video-shell">
            <div class="video-frame"><iframe id="video-frame" src="" title="Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
            <div class="video-bar"><button type="button" id="video-close">বন্ধ করুন ✕</button></div>
        </div>
    </dialog>
@endsection
