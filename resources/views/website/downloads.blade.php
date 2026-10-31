@extends('layouts.public')

@section('title', 'ডাউনলোড')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / ডাউনলোড</div><h1>ডাউনলোড</h1><p>সিলেবাস, ফরম ও গুরুত্বপূর্ণ ফাইল</p></div></section>

    <section class="section"><div class="container">
        <div class="card article">
            <h2>ফাইলসমূহ</h2>
            <div class="download-list">
                @forelse ($items as $item)
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 5px;border-bottom:1px solid #e8ecf1;gap:12px">
                        <div>
                            <strong>{{ $item->title }}</strong>
                            @if ($item->excerpt)<div class="muted" style="font-size:13px">{{ $item->excerpt }}</div>@endif
                            @if ($item->category)<span class="badge">{{ $item->category }}</span>@endif
                        </div>
                        @if ($item->file_url)
                            <a class="btn btn-outline btn-sm" href="{{ $item->file_url }}" target="_blank" rel="noopener">ডাউনলোড</a>
                        @else
                            <span class="badge">শীঘ্রই</span>
                        @endif
                    </div>
                @empty
                    <p>বর্তমানে কোনো ফাইল প্রকাশ করা হয়নি।</p>
                @endforelse
            </div>
            <div style="margin-top:16px">{{ $items->links() }}</div>
        </div>

        @if (isset($syllabusItems) && $syllabusItems->isNotEmpty())
            <div class="card article" style="margin-top:18px">
                <h2>সিলেবাস</h2>
                <div class="download-list">
                    @foreach ($syllabusItems as $item)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 5px;border-bottom:1px solid #e8ecf1;gap:12px">
                            <div>
                                <strong>{{ $item->title }}</strong>
                                @if ($item->excerpt)<div class="muted" style="font-size:13px">{{ $item->excerpt }}</div>@endif
                                @if ($item->category)<span class="badge">{{ $item->category }}</span>@endif
                            </div>
                            @if ($item->file_url)
                                <a class="btn btn-outline btn-sm" href="{{ $item->file_url }}" target="_blank" rel="noopener">ডাউনলোড</a>
                            @else
                                <span class="badge">শীঘ্রই</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div></section>
@endsection
