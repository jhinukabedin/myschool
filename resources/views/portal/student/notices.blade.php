@extends('layouts.portal')

@section('title', 'Notice Board')
@section('banner-subtitle', 'বিদ্যালয়ের সর্বশেষ বিজ্ঞপ্তি ও ঘোষণা')

@section('content')
    <div class="portal-panel">
        <h2>সর্বশেষ নোটিশ</h2>
        <div class="portal-panel-body">
            <div class="download-list">
                @forelse ($notices as $notice)
                    <a href="{{ route('student.notices.show', $notice) }}">
                        <span>
                            <b>{{ $notice->title }}</b>
                            <small style="display:block;color:#7a8592">
                                {{ Str::limit($notice->plain_body, 110) }}
                            </small>
                        </span>
                        <b>{{ ($notice->publish_at ?? $notice->created_at)?->format('d M Y') }}</b>
                    </a>
                @empty
                    <div class="empty">
                        <strong>কোনো নোটিশ প্রকাশিত হয়নি।</strong><br>
                        নতুন বিজ্ঞপ্তি প্রকাশ হলে এখানে দেখা যাবে।
                    </div>
                @endforelse
            </div>

            @if ($notices->hasPages())
                <div style="margin-top:16px">{{ $notices->links() }}</div>
            @endif
        </div>
    </div>
@endsection
