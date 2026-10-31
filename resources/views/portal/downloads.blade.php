@extends('layouts.portal')

@section('title', 'Downloads')
@section('banner-subtitle', 'সিলেবাস, ফরম ও অন্যান্য ডাউনলোডযোগ্য ফাইল')

@section('content')
    <div class="portal-panel">
        <h2>ডাউনলোড</h2>
        <div class="portal-panel-body">
            <div class="download-list">
                @forelse ($downloads as $download)
                    <a href="{{ $download->file_url ?: '#' }}" @if ($download->file_url) target="_blank" rel="noopener" @else onclick="return false" @endif>
                        <span>
                            <b>{{ $download->title }}</b>
                            <small style="display:block;color:#7a8592">
                                {{ $download->category ?: 'Resource' }}
                                @if ($download->excerpt) · {{ $download->excerpt }} @endif
                                @if ($download->published_at) · {{ $download->published_at->format('d M Y') }} @endif
                            </small>
                        </span>
                        <b>{{ $download->file_url ? '↓' : '—' }}</b>
                    </a>
                @empty
                    <div class="empty">
                        <strong>কোনো ফাইল প্রকাশিত হয়নি।</strong><br>
                        স্কুল অফিস ফাইল প্রকাশ করলে এখানে দেখা যাবে।
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
