@extends('layouts.public')

@section('title', 'প্রধান শিক্ষকের বাণী')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / পরিচিতি / বাণী</div><h1>প্রধান শিক্ষকের বাণী</h1><p>আমাদের শিক্ষার্থী ও অভিভাবকদের প্রতি</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            @if ($message)
                @if ($message->image_url)
                    <img class="message-portrait" src="{{ $message->image_url }}" alt="{{ $message->title }}">
                @endif
                <h2>{{ $message->title }}</h2>
                @if ($message->excerpt)<p class="lead-text">{{ $message->excerpt }}</p>@endif
                <div>{!! $message->body_html !!}</div>
                <p style="margin-top:20px"><b>{{ $message->category ?: 'প্রধান শিক্ষক' }}</b><br>{{ $siteNameBn }}</p>
            @elseif ($page)
                <h2>{{ $page->title }}</h2>
                <div>{!! $page->body_html !!}</div>
            @else
                <h2>শিক্ষার্থীর সম্ভাবনাকে বিকশিত করাই আমাদের লক্ষ্য</h2>
                <p>প্রিয় শিক্ষার্থী ও অভিভাবকবৃন্দ,</p>
                <p>প্রতিটি শিক্ষার্থীর ভেতরে আলাদা সম্ভাবনা রয়েছে। সঠিক দিকনির্দেশনা, অধ্যবসায় ও সুন্দর পরিবেশের মাধ্যমে সেই সম্ভাবনাকে বিকশিত করাই আমাদের প্রধান লক্ষ্য। {{ $siteNameBn }}-এ আমরা শুধু পরীক্ষার ফলাফল নয়, বরং একজন দায়িত্বশীল, মানবিক ও আত্মনির্ভর মানুষ গড়ে তুলতে বিশ্বাস করি।</p>
                <p>আমাদের অভিজ্ঞ শিক্ষকমণ্ডলী, সুশৃঙ্খল একাডেমিক কার্যক্রম এবং সহশিক্ষা কার্যক্রমের মাধ্যমে শিক্ষার্থীদের সর্বাঙ্গীণ বিকাশে আমরা প্রতিশ্রুতিবদ্ধ। অভিভাবকদের সক্রিয় সহযোগিতা এই যাত্রাকে আরও শক্তিশালী করে।</p>
                <p>আমি সকল শিক্ষার্থীর উজ্জ্বল ভবিষ্যৎ কামনা করি।</p>
                <p style="margin-top:20px"><b>প্রধান শিক্ষক</b><br>{{ $siteNameBn }}</p>
            @endif
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            @include('website._sidebar')
        </aside>
    </div></section>
@endsection

@push('head')
    <style>
        .message-portrait { max-width: 180px; max-height: 220px; object-fit: cover; border-radius: 12px; float: right; margin: 0 0 18px 24px; }
        .article .lead-text { font-size: 16px; color: var(--navy); font-weight: 600; }
        @media (max-width: 640px) { .message-portrait { float: none; display: block; margin: 0 0 16px; } }
    </style>
@endpush
