@extends('layouts.public')

@php
    $meta = [
        'administration_message' => [
            'title' => 'প্রশাসনের বাণী',
            'eyebrow' => 'প্রশাসন',
            'role' => 'প্রশাসন',
            'fallback' => 'আমাদের বিদ্যালয়ের প্রতিটি কার্যক্রমের কেন্দ্রে রয়েছে শিক্ষার্থী। সুসংগঠিত প্রশাসন, আন্তরিক শিক্ষকমণ্ডলী এবং সচেতন অভিভাবকের সমন্বয়ে আমরা এমন একটি পরিবেশ তৈরি করতে চাই যেখানে প্রতিটি শিক্ষার্থী নিরাপদে শিখতে, প্রশ্ন করতে এবং নিজের সম্ভাবনা বিকশিত করতে পারবে।',
        ],
        'president_message' => [
            'title' => 'সভাপতির বাণী',
            'eyebrow' => 'পরিচালনা পর্ষদ',
            'role' => 'সভাপতি',
            'fallback' => 'শিক্ষা কেবল ভালো ফলাফলের জন্য নয়—এটি একজন মানুষকে দায়িত্বশীল, মানবিক ও আত্মনির্ভর করে গড়ে তোলে। আমাদের বিদ্যালয় জ্ঞান, নৈতিকতা ও দেশপ্রেমের সমন্বয়ে শিক্ষার্থীদের ভবিষ্যতের জন্য প্রস্তুত করতে প্রতিশ্রুতিবদ্ধ।',
        ],
    ][$messageType] ?? [
        'title' => 'বাণী',
        'eyebrow' => 'বিদ্যালয়',
        'role' => 'শিক্ষক',
        'fallback' => '',
    ];
@endphp

@section('title', $message?->title ?: $meta['title'])

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / পরিচিতি / বাণী</div><h1>{{ $meta['title'] }}</h1><p>{{ $siteNameBn }} · {{ $meta['eyebrow'] }}</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            @if ($message)
                @if ($message->image_url)
                    <img class="message-portrait" src="{{ $message->image_url }}" alt="{{ $message->title }}">
                @endif
                <h2>{{ $message->title }}</h2>
                @if ($message->excerpt)
                    <p class="lead-text">{{ $message->excerpt }}</p>
                @endif
                <div>{!! $message->body_html !!}</div>
                <p style="margin-top:24px"><b>{{ $message->category ?: $meta['role'] }}</b><br>{{ $siteNameBn }}</p>
            @elseif ($page)
                <h2>{{ $page->title }}</h2>
                <div>{!! $page->body_html !!}</div>
            @else
                <h2>{{ $meta['title'] }}</h2>
                <p>{{ $meta['fallback'] }}</p>
                <p style="margin-top:24px"><b>{{ $meta['role'] }}</b><br>{{ $siteNameBn }}</p>
            @endif
        </article>

        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">আরও দেখুন</h3>
                <a href="{{ route('website.about') }}" style="display:block;padding:7px 0">বিদ্যালয় পরিচিতি</a>
                <a href="{{ route('website.administration-message') }}" style="display:block;padding:7px 0">প্রশাসনের বাণী</a>
                <a href="{{ route('website.principal-message') }}" style="display:block;padding:7px 0">প্রধান শিক্ষকের বাণী</a>
                <a href="{{ route('website.president-message') }}" style="display:block;padding:7px 0">সভাপতির বাণী</a>
                <a href="{{ route('website.faculty') }}" style="display:block;padding:7px 0">শিক্ষকবৃন্দ</a>
            </div>
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">যোগাযোগ</h3>
                @if ($siteAddress)<p>{{ $siteAddress }}</p>@endif
                @if ($sitePhone)<p>ফোন: {{ $sitePhone }}</p>@endif
                @if ($siteEmail)<p>ইমেইল: {{ $siteEmail }}</p>@endif
            </div>
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
