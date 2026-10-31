@extends('layouts.portal')

@section('title', 'Notice Board')
@section('banner-subtitle', 'বিদ্যালয়ের সর্বশেষ বিজ্ঞপ্তি ও ঘোষণা')

@section('content')
    <div class="portal-panel">
        <h2>{{ $notice->title }}</h2>
        <div class="portal-panel-body">
            <div class="chips" style="margin-bottom:14px">
                <span class="chip">{{ ($notice->publish_at ?? $notice->created_at)?->format('d M Y') }}</span>
                <span class="chip">{{ $notice->audience_label }}</span>
                @if ($notice->expires_at)
                    <span class="chip">Valid until {{ $notice->expires_at->format('d M Y') }}</span>
                @endif
            </div>

            <div class="notice-body" style="font-size:15px;line-height:1.8">
                {!! $notice->rendered_body !!}
            </div>

            <p style="margin:20px 0 0">
                <a class="btn btn-outline btn-sm" href="{{ route('student.notices') }}">← সব নোটিশ</a>
            </p>
        </div>
    </div>
@endsection
