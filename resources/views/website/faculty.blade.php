@extends('layouts.public')

@section('title', 'শিক্ষকবৃন্দ')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / শিক্ষকবৃন্দ</div><h1>শিক্ষকবৃন্দ</h1><p>আমাদের অভিজ্ঞ ও নিবেদিত শিক্ষকমণ্ডলী</p></div></section>

    <section class="section"><div class="container">
        <div class="section-head"><h2 class="section-title">শিক্ষক তালিকা</h2><span class="badge">{{ $profiles->count() }} জন</span></div>
        <div class="academic-grid">
            @forelse ($profiles as $profile)
                <article class="card article reveal" style="text-align:center">
                    @if ($profile->image_url)
                        <img src="{{ $profile->image_url }}" alt="{{ $profile->title }}" style="width:110px;height:110px;border-radius:50%;object-fit:cover;margin:0 auto 12px">
                    @else
                        <div class="person-photo" style="margin:0 auto 12px">{{ Str::substr($profile->title, 0, 1) }}</div>
                    @endif
                    <h3 style="margin:0 0 4px">{{ $profile->title }}</h3>
                    @if ($profile->category)<p class="badge" style="display:inline-block">{{ $profile->category }}</p>@endif
                    @if ($profile->excerpt)<p style="margin-top:10px">{{ $profile->excerpt }}</p>@endif
                    @if ($profile->body)<div style="margin-top:8px">{!! $profile->body_html !!}</div>@endif
                </article>
            @empty
                <div class="card article" style="grid-column:1/-1"><p>শিক্ষকদের তথ্য শীঘ্রই প্রকাশিত হবে। বিদ্যালয় অফিসের সাথে যোগাযোগ করুন।</p></div>
            @endforelse
        </div>
    </div></section>
@endsection
