@extends('layouts.public')

@section('title', $introduction?->title ?: 'বিদ্যালয় পরিচিতি')

@section('content')
    <section class="page-hero"><div class="container"><div class="breadcrumb">প্রচ্ছদ / পরিচিতি</div><h1>{{ $introduction?->title ?: 'বিদ্যালয় পরিচিতি' }}</h1><p>{{ $introduction?->subtitle ?: $siteNameBn.' · '.$siteNameEn }}</p></div></section>

    <section class="section"><div class="container inner-grid">
        <article class="card article">
            @if ($introduction)
                <div>{!! $introduction->body_html !!}</div>
            @endif

            @if ($page)
                <div>{!! $page->body_html !!}</div>
            @endif

            @if (! $introduction && ! $page)
                <h2 id="history">আমাদের বিদ্যালয়</h2>
                <p>{{ $siteNameBn }} একটি আধুনিক শিক্ষা প্রতিষ্ঠান, যেখানে জ্ঞান, শৃঙ্খলা ও নৈতিকতার সমন্বয়ে শিক্ষার্থীদের ভবিষ্যতের জন্য প্রস্তুত করা হয়। অভিজ্ঞ শিক্ষকমণ্ডলী, সুশৃঙ্খল একাডেমিক কার্যক্রম এবং সহশিক্ষা কার্যক্রমের মাধ্যমে শিক্ষার্থীদের মেধা ও মানবিক মূল্যবোধের বিকাশে আমরা কাজ করি।</p>
                <p>আমাদের লক্ষ্য একটি নিরাপদ, আনন্দময় ও অন্তর্ভুক্তিমূলক শিক্ষার পরিবেশ তৈরি করা, যেখানে প্রতিটি শিক্ষার্থী নিজের সর্বোচ্চ সম্ভাবনায় পৌঁছাতে পারে।</p>
            @endif
        </article>

        <aside class="stack" style="display:flex;flex-direction:column;gap:14px">
            @include('website._sidebar')
            <div class="card contact-card">
                <h3 style="margin:0 0 10px;color:var(--navy);font-family:'Noto Serif Bengali'">যোগাযোগ</h3>
                @if ($siteAddress)<p>{{ $siteAddress }}</p>@endif
                @if ($sitePhone)<p>ফোন: {{ $sitePhone }}</p>@endif
                @if ($siteEmail)<p>ইমেইল: {{ $siteEmail }}</p>@endif
            </div>
        </aside>
    </div></section>
@endsection
