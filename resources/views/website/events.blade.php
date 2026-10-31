@extends('layouts.public')

@section('title', 'ইভেন্ট')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / ইভেন্ট</div><h1>ইভেন্ট</h1><p>আসন্ন ও সাম্প্রতিক অনুষ্ঠান</p></div></section>

    <section class="section"><div class="container">
        <div class="academic-grid" style="grid-template-columns:repeat(2,1fr)">
            @forelse ($items as $item)
                <article class="card article reveal">
                    @if ($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" style="width:100%;height:170px;object-fit:cover;border-radius:8px;margin-bottom:12px">@endif
                    <span class="badge gold">{{ $item->category ?: 'ইভেন্ট' }}</span>
                    <h3 style="margin-top:8px">{{ $item->title }}</h3>
                    <small class="muted">{{ $item->published_at?->format('d M Y') }}</small>
                    <p>{{ $item->excerpt ?: Str::limit(strip_tags($item->body), 180) }}</p>
                </article>
            @empty
                <div class="card article" style="grid-column:1/-1"><p>বর্তমানে কোনো প্রকাশিত ইভেন্ট নেই।</p></div>
            @endforelse
        </div>
        <div style="margin-top:16px">{{ $items->links() }}</div>
    </div></section>
@endsection
