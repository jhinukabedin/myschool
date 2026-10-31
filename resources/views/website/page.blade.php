@extends('layouts.public')

@section('title', $page->title)
@section('meta_description', $page->meta_description ?: Str::limit(strip_tags($page->excerpt ?: $page->body), 155))

@push('head')
    @if ($page->og_image_url)
        <meta property="og:image" content="{{ $page->og_image_url }}">
    @endif
    <meta property="og:title" content="{{ $page->meta_title ?: $page->title }}">
    <meta property="og:type" content="article">
@endpush

@section('content')
    <section class="page-hero"><div class="container">
        <div class="breadcrumb">প্রচ্ছদ / {{ $page->title }}</div>
        <h1>{{ $page->title }}</h1>
        @if ($page->excerpt)<p>{{ $page->excerpt }}</p>@endif
    </div></section>

    <section class="section"><div class="container article-layout">
        <article class="card article">
            @if ($page->image_url)
                <img src="{{ $page->image_url }}" alt="{{ $page->title }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:10px;margin-bottom:18px">
            @endif
            <div>{!! $page->body_html !!}</div>
            @if ($page->file_url)
                <p style="margin-top:22px"><a class="btn btn-primary" href="{{ $page->file_url }}" target="_blank" rel="noopener">Download attachment</a></p>
            @endif
        </article>
    </div></section>
@endsection
