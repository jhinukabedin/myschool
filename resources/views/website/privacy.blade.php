@extends('layouts.public')

@section('title', 'গোপনীয়তা নীতি')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / গোপনীয়তা</div><h1>গোপনীয়তা নীতি</h1><p>আমরা কীভাবে তথ্য সংগ্রহ ও ব্যবহার করি</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            @if ($page)
                <div>{!! $page->body_html !!}</div>
            @else
                <h2>তথ্য সংগ্রহ</h2>
                <p>যোগাযোগ ফর্ম ও ভর্তি আবেদনের মাধ্যমে আমরা নাম, ফোন নম্বর, ইমেইল ও শিক্ষার্থী সম্পর্কিত প্রয়োজনীয় তথ্য সংগ্রহ করি। এই তথ্য শুধুমাত্র যোগাযোগ এবং ভর্তি প্রক্রিয়ার জন্য ব্যবহৃত হয়।</p>
                <h2>তথ্যের ব্যবহার</h2>
                <ul>
                    <li>আবেদন ও যোগাযোগের উত্তর দেওয়ার জন্য।</li>
                    <li>ভর্তি প্রক্রিয়া পরিচালনার জন্য।</li>
                    <li>বিদ্যালয়ের আইনি ও প্রশাসনিক বাধ্যবাধকতা পূরণের জন্য।</li>
                </ul>
                <h2>তথ্যের সুরক্ষা</h2>
                <p>আমরা অননুমোদিত প্রবেশ থেকে তথ্য সুরক্ষিত রাখতে যুক্তিসঙ্গত প্রশাসনিক ও কারিগরি ব্যবস্থা গ্রহণ করি। শিক্ষার্থী ও অভিভাবকের তথ্য তৃতীয় পক্ষের কাছে বিক্রি করা হয় না।</p>
                <h2>আপনার অধিকার</h2>
                <p>আপনি আপনার সংরক্ষিত তথ্য সম্পর্কে জানতে, সংশোধন করতে বা মুছে ফেলার অনুরোধ করতে পারেন। যোগাযোগ করুন: {{ $siteEmail ?: $sitePhone }}</p>
            @endif
        </article>
        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            @include('website._sidebar')
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">যোগাযোগ</h3>
                @if ($siteAddress)<p>{{ $siteAddress }}</p>@endif
                @if ($sitePhone)<p>ফোন: {{ $sitePhone }}</p>@endif
                @if ($siteEmail)<p>ইমেইল: {{ $siteEmail }}</p>@endif
                <a href="{{ route('website.contact') }}">যোগাযোগ ফর্ম →</a>
            </div>
        </aside>
    </div></section>
@endsection
