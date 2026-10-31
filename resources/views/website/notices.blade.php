@extends('layouts.public')

@section('title', 'নোটিশ বোর্ড')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / নোটিশ</div><h1>নোটিশ বোর্ড</h1><p>সর্বশেষ ঘোষণা ও তথ্য</p></div></section>

    <section class="section"><div class="container">
        <div class="card article">
            <div class="section-head" style="margin-bottom:6px">
                <h2 style="margin:0">সকল নোটিশ</h2>
                <form method="GET" action="{{ route('website.notices') }}" style="display:flex;gap:8px">
                    <input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="নোটিশ খুঁজুন" style="border:1px solid #dbe1e8;border-radius:8px;padding:9px 11px">
                    <button class="btn btn-outline" type="submit">খুঁজুন</button>
                </form>
            </div>

            <div class="timeline" style="margin-top:18px">
                @forelse ($notices as $notice)
                    <div class="timeline-item">
                        <small>{{ $notice->publish_at?->format('d M Y') ?? $notice->created_at?->format('d M Y') }}</small>
                        <b><a href="{{ route('website.notice', $notice) }}">{{ $notice->title }}</a></b>
                        <p style="margin:4px 0 0">{{ Str::limit($notice->plain_body, 140) }}</p>
                    </div>
                @empty
                    <p>বর্তমানে কোনো প্রকাশিত নোটিশ নেই।</p>
                @endforelse
            </div>

            <div style="margin-top:16px">{{ $notices->links() }}</div>
        </div>
    </div></section>
@endsection
