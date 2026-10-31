@extends('layouts.public')

@section('title', 'ক্লাস রুটিন')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / একাডেমিক / ক্লাস রুটিন</div><h1>ক্লাস রুটিন</h1><p>শ্রেণিভিত্তিক সময়সূচি ও ক্লাস রুটিন ডাউনলোড</p></div></section>

    <section class="section"><div class="container">
        <div class="card article">
            <h2>রুটিন তালিকা</h2>
            <div class="download-list">
                @forelse ($items as $item)
                    <div style="padding:14px 5px;border-bottom:1px solid #e8ecf1">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                            <div>
                                <strong>{{ $item->title }}</strong>
                                @if ($item->category)<span class="badge">{{ $item->category }}</span>@endif
                                @if ($item->excerpt)<div class="muted" style="font-size:13px">{{ $item->excerpt }}</div>@endif
                            </div>
                            @if ($item->file_url)
                                <a class="btn btn-outline btn-sm" href="{{ $item->file_url }}" target="_blank" rel="noopener">ডাউনলোড</a>
                            @else
                                <span class="badge">শীঘ্রই</span>
                            @endif
                        </div>
                        @if (trim(strip_tags((string) $item->body)))
                            <div style="margin-top:10px">{!! $item->body_html !!}</div>
                        @endif
                    </div>
                @empty
                    <p>বর্তমানে কোনো ক্লাস রুটিন প্রকাশ করা হয়নি।</p>
                @endforelse
            </div>
        </div>
    </div></section>
@endsection
