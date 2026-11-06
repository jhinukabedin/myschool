@extends('layouts.public')

@section('title', 'একাডেমিক ক্যালেন্ডার')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / শিক্ষাক্রম / ক্যালেন্ডার</div><h1>একাডেমিক ক্যালেন্ডার</h1><p>{{ $session?->name ?? 'চলমান' }} সেশনের পরীক্ষা ও অনুষ্ঠান</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            <h2>পরীক্ষার সময়সূচি</h2>
            <div class="timeline">
                @forelse ($exams as $exam)
                    <div class="timeline-item">
                        <small>{{ $exam->start_date?->format('d M Y') }}@if ($exam->end_date) – {{ $exam->end_date->format('d M Y') }}@endif</small>
                        <b>{{ $exam->name }}</b>
                        <p style="margin:4px 0 0">শ্রেণি: {{ $exam->subjects->pluck('class_id')->unique()->count() }} · অবস্থা: {{ ucfirst($exam->status) }}</p>
                    </div>
                @empty
                    <p>এই সেশনে কোনো পরীক্ষার সময়সূচি প্রকাশিত হয়নি।</p>
                @endforelse
            </div>

            <h2 style="margin-top:24px">অনুষ্ঠান</h2>
            <div class="timeline">
                @forelse ($events as $event)
                    <div class="timeline-item">
                        <small>{{ $event->published_at?->format('d M Y') }}</small>
                        <b>{{ $event->title }}</b>
                        <p style="margin:4px 0 0">{{ $event->excerpt ?: Str::limit(strip_tags($event->body), 120) }}</p>
                    </div>
                @empty
                    <p>বর্তমানে কোনো প্রকাশিত অনুষ্ঠান নেই।</p>
                @endforelse
            </div>

            @if (isset($calendarItems) && $calendarItems->isNotEmpty())
                <h2 style="margin-top:24px">একাডেমিক ক্যালেন্ডার</h2>
                <div class="timeline">
                    @foreach ($calendarItems as $item)
                        <div class="timeline-item">
                            <small>{{ $item->published_at?->format('d M Y') ?? ($item->category ?: 'ক্যালেন্ডার') }}</small>
                            <b>{{ $item->title }}</b>
                            @if ($item->excerpt)<p style="margin:4px 0 0">{{ $item->excerpt }}</p>@endif
                            @if (trim(strip_tags((string) $item->body)))<div style="margin-top:6px">{!! $item->body_html !!}</div>@endif
                            @if ($item->file_url)<p style="margin:6px 0 0"><a class="btn btn-outline btn-sm" href="{{ $item->file_url }}" target="_blank" rel="noopener">ডাউনলোড</a></p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            @include('website._sidebar')
        </aside>
    </div></section>
@endsection
