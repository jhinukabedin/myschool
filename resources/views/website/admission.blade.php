@extends('layouts.public')

@section('title', 'ভর্তি তথ্য')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / ভর্তি</div><h1>ভর্তি তথ্য</h1><p>ভর্তি প্রক্রিয়া, যোগ্যতা ও প্রয়োজনীয় কাগজপত্র</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            @if ($page)
                <div>{!! $page->body_html !!}</div>
            @else
                <h2>ভর্তি বিজ্ঞপ্তি</h2>
                <p>{{ $siteNameBn }}-এ নতুন শিক্ষাবর্ষের ভর্তি কার্যক্রম চলছে। আগ্রহী অভিভাবকগণ নিচের তথ্য অনুসরণ করে আবেদন করতে পারেন।</p>
                <h3>ভর্তির যোগ্যতা</h3>
                <ul>
                    <li>নির্ধারিত বয়সসীমা পূরণ করতে হবে।</li>
                    <li>পূর্ববর্তী শ্রেণির ফলাফল ও শিক্ষাপ্রতিষ্ঠানের প্রত্যয়নপত্র প্রয়োজন।</li>
                    <li>ভর্তি পরীক্ষা/সাক্ষাৎকারে অংশগ্রহণ করতে হবে (প্রযোজ্য ক্ষেত্রে)।</li>
                </ul>
                <h3 id="process">ভর্তি প্রক্রিয়া</h3>
                <ol>
                    <li>অনলাইন আবেদন ফরম পূরণ করুন।</li>
                    <li>বিদ্যালয় অফিস থেকে আবেদন যাচাই করিয়ে নিন।</li>
                    <li>প্রয়োজনীয় কাগজপত্র জমা দিন।</li>
                    <li>ভর্তি পরীক্ষা/সাক্ষাৎকারে অংশগ্রহণ করুন।</li>
                    <li>নির্বাচিত হলে নির্ধারিত ফি পরিশোধ করে ভর্তি সম্পন্ন করুন।</li>
                </ol>
                <h3>প্রয়োজনীয় কাগজপত্র</h3>
                <ul>
                    <li>শিক্ষার্থীর জন্মনিবন্ধনের কপি</li>
                    <li>অভিভাবকের জাতীয় পরিচয়পত্রের কপি</li>
                    <li>পূর্ববর্তী প্রতিষ্ঠানের প্রত্যয়নপত্র ও ফলাফল</li>
                    <li>সাম্প্রতিক পাসপোর্ট সাইজের ছবি</li>
                </ul>
            @endif

            @if (isset($admissionItems) && $admissionItems->isNotEmpty())
                <h2 style="margin-top:26px">ভর্তি তথ্য ও বিজ্ঞপ্তি</h2>
                <div class="download-list">
                    @foreach ($admissionItems as $item)
                        <div style="padding:14px 0;border-bottom:1px solid #e8ecf1">
                            <strong>{{ $item->title }}</strong>
                            @if ($item->category)<span class="badge">{{ $item->category }}</span>@endif
                            @if ($item->excerpt)<div class="muted" style="font-size:13px">{{ $item->excerpt }}</div>@endif
                            @if (trim(strip_tags((string) $item->body)))<div style="margin-top:8px">{!! $item->body_html !!}</div>@endif
                            @if ($item->image_url)<img src="{{ $item->image_url }}" alt="{{ $item->title }}" style="max-width:100%;border-radius:10px;margin-top:10px">@endif
                            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                                @if ($item->file_url)<a class="btn btn-outline btn-sm" href="{{ $item->file_url }}" target="_blank" rel="noopener">ডাউনলোড</a>@endif
                                @if ($item->link_url)<a class="btn btn-outline btn-sm" href="{{ $item->link_url }}">{{ $item->button_label ?: 'বিস্তারিত' }}</a>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <p style="margin-top:18px"><a class="btn btn-purple" href="{{ route('website.admission.apply') }}">অনলাইনে আবেদন করুন</a></p>
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            @include('website._sidebar')
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">ক্লাসসমূহ</h3>
                @forelse ($classes as $class)
                    <div style="padding:5px 0">{{ $class->name }}</div>
                @empty
                    <p>ক্লাস তথ্য শীঘ্রই প্রকাশিত হবে।</p>
                @endforelse
            </div>
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">ভর্তি সহায়তা</h3>
                @if ($sitePhone)<p>ফোন: {{ $sitePhone }}</p>@endif
                @if ($siteEmail)<p>ইমেইল: {{ $siteEmail }}</p>@endif
                <a href="{{ route('website.contact') }}">যোগাযোগ ফর্ম →</a>
            </div>
        </aside>
    </div></section>
@endsection
