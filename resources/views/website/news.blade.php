@extends('layouts.public')

@section('title', 'সংবাদ')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / সংবাদ</div><h1>সংবাদ</h1><p>বিদ্যালয়ের সর্বশেষ খবর</p></div></section>

    <section class="section"><div class="container">
        <div class="card article" style="margin-bottom:18px">
            <form method="GET" action="{{ route('website.news') }}" style="display:flex;gap:8px;flex-wrap:wrap">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="সংবাদ খুঁজুন" style="flex:1;min-width:200px;border:1px solid #dbe1e8;border-radius:8px;padding:10px 12px">
                <button class="btn btn-outline" type="submit">খুঁজুন</button>
            </form>
        </div>

        <div class="news-grid">
            @forelse ($items as $item)
                <article class="card article reveal">
                    @if ($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" style="width:100%;height:180px;object-fit:cover;border-radius:8px;margin-bottom:12px">@endif
                    <small class="muted">{{ $item->published_at?->format('d M Y') }}</small>
                    <h3 style="margin-top:6px"><a href="{{ route('website.news.item', $item->slug) }}">{{ $item->title }}</a></h3>
                    <p>{{ $item->excerpt ?: Str::limit(strip_tags($item->body), 150) }}</p>
                    <a class="btn btn-outline" href="{{ route('website.news.item', $item->slug) }}">বিস্তারিত →</a>
                </article>
            @empty
                <div class="card article" style="grid-column:1/-1"><p>বর্তমানে কোনো প্রকাশিত সংবাদ নেই।</p></div>
            @endforelse
        </div>
        <div style="margin-top:16px">{{ $items->links() }}</div>
    </div></section>
@endsection
