@extends('layouts.portal')

@section('title', 'Academic Calendar')
@section('banner-subtitle', 'শিক্ষাবর্ষের গুরুত্বপূর্ণ তারিখ')

@section('content')
    <div class="portal-panel">
        <h2>{{ $session?->name ?? 'শিক্ষাবর্ষের' }} ক্যালেন্ডার</h2>
        <div class="portal-panel-body">
            <div class="download-list">
                @forelse ($items as $item)
                    <a href="{{ $item->file_url ?: '#' }}" @if ($item->file_url) target="_blank" rel="noopener" @else onclick="return false" @endif>
                        <span>
                            <b>{{ $item->title }}</b>
                            <small style="display:block;color:#7a8592">
                                {{ $item->excerpt ?: Str::limit(strip_tags($item->body), 110) }}
                            </small>
                        </span>
                        <b>{{ $item->published_at?->format('d M') ?? ($item->file_url ? '↓' : '') }}</b>
                    </a>
                @empty
                    <div class="empty">
                        <strong>এখনও কোনো ক্যালেন্ডার এন্ট্রি প্রকাশিত হয়নি।</strong><br>
                        স্কুল অফিস তারিখ প্রকাশ করলে এখানে দেখা যাবে।
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="portal-panel" style="margin-top:16px">
        <h2>পরীক্ষার সময়সূচি</h2>
        <div class="portal-panel-body">
            <div class="table-wrap">
                <table class="portal-table">
                    <thead>
                        <tr><th>তারিখ</th><th>পরীক্ষা</th><th>বিষয়</th><th>সময়</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($exams as $paper)
                            <tr>
                                <td class="mono">{{ $paper->exam_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $paper->examination?->name ?? '—' }}</td>
                                <td>{{ $paper->subject?->name ?? '—' }}</td>
                                <td class="mono">{{ $paper->start_time ? substr($paper->start_time, 0, 5) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">কোনো প্রকাশিত পরীক্ষার সময়সূচি নেই।</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p style="margin:16px 0 0">
                <a class="btn btn-outline btn-sm" href="{{ route('website.calendar') }}">পাবলিক ক্যালেন্ডার</a>
            </p>
        </div>
    </div>
@endsection
