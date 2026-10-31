@extends('layouts.public')

@section('title', $notice->title)

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / নোটিশ</div><h1>{{ $notice->title }}</h1><p>{{ $notice->publish_at?->format('d M Y') ?? $notice->created_at?->format('d M Y') }}</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            <div style="margin-bottom:12px"><span class="badge green">প্রকাশিত</span></div>
            <div>{!! $notice->rendered_body !!}</div>
            <hr style="border:0;border-top:1px solid #edf0f4;margin:22px 0">
            <a class="btn btn-outline" href="{{ route('website.notices') }}">← সব নোটিশ</a>
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">সাম্প্রতিক নোটিশ</h3>
                @forelse ($related as $item)
                    <a href="{{ route('website.notice', $item) }}" style="display:block;padding:6px 0">{{ $item->title }}</a>
                @empty
                    <p>আর কোনো নোটিশ নেই।</p>
                @endforelse
            </div>
        </aside>
    </div></section>
@endsection
