@extends('layouts.public')

@section('title', 'শিক্ষাক্রম')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / শিক্ষাক্রম</div><h1>শিক্ষাক্রম</h1><p>ক্লাসভিত্তিক বিষয় ও একাডেমিক কার্যক্রম</p></div></section>

    <section class="section"><div class="container">
        <div class="section-head"><h2 class="section-title">একাডেমিক কার্যক্রম</h2><span class="badge">{{ $session?->name ?? 'চলমান সেশন' }}</span></div>
        <div class="card article" style="margin-bottom:18px">
            <p>আমাদের শিক্ষাক্রম শিক্ষার্থীদের মৌলিক জ্ঞান, সৃজনশীলতা ও সমস্যা সমাধানের দক্ষতা গড়ে তোলার লক্ষ্যে সাজানো হয়েছে। প্রতিটি ক্লাসের জন্য নির্ধারিত বিষয়, পাঠ্যক্রম ও মূল্যায়ন পদ্ধতি নিচে দেওয়া হয়েছে।</p>
            <p><a href="{{ route('website.downloads') }}">সিলেবাস ও গুরুত্বপূর্ণ ফাইল ডাউনলোড করুন →</a> · <a href="{{ route('website.calendar') }}">একাডেমিক ক্যালেন্ডার দেখুন →</a></p>
        </div>

        <div id="classes" class="academic-grid" style="grid-template-columns:repeat(2,1fr)">
            @forelse ($classes as $class)
                <article class="card article">
                    <h3 style="margin-top:0">{{ $class->name }}</h3>
                    @if ($class->subjects->isEmpty())
                        <p>এই ক্লাসের বিষয়সমূহ শীঘ্রই প্রকাশিত হবে।</p>
                    @else
                        <ul>
                            @foreach ($class->subjects as $mapping)
                                <li>{{ $mapping->subject?->name ?? 'বিষয়' }}</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @empty
                <div class="card article"><p>এখনো কোনো ক্লাস বা বিষয় কনফিগার করা হয়নি।</p></div>
            @endforelse
        </div>
    </div></section>
@endsection
