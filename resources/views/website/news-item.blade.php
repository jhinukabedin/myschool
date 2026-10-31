@extends('layouts.public')

@section('title', $item->title)

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / সংবাদ</div><h1>{{ $item->title }}</h1><p>{{ $item->published_at?->format('d M Y') }}</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            @if ($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" style="width:100%;max-height:360px;object-fit:cover;border-radius:8px;margin-bottom:16px">@endif
            @if ($item->excerpt)<p style="font-size:17px;color:#3f4c5b"><strong>{{ $item->excerpt }}</strong></p>@endif
            <div>{!! $item->body_html !!}</div>
            <hr style="border:0;border-top:1px solid #edf0f4;margin:22px 0">
            <a class="btn btn-outline" href="{{ route('website.news') }}">← সব সংবাদ</a>
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">আরও সংবাদ</h3>
                @forelse ($related as $other)
                    <a href="{{ route('website.news.item', $other->slug) }}" style="display:block;padding:6px 0">{{ $other->title }}</a>
                @empty
                    <p>আর কোনো সংবাদ নেই।</p>
                @endforelse
            </div>
        </aside>
    </div></section>
@endsection
