@php
    // Identity values are supplied by the public website view composer.
@endphp
<!doctype html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="@yield('meta_description', $siteMetaDescription)">
    <title>@yield('title', 'প্রচ্ছদ') | {{ $siteNameBn }}</title>
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&family=Noto+Serif+Bengali:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('website/style.css') }}?v={{ @filemtime(public_path('website/style.css')) ?: '1' }}">
    @stack('head')
</head>
@php
    $ctaHref = $siteHeaderCtaUrl
        ? (preg_match('#^(https?://|mailto:|tel:)#i', $siteHeaderCtaUrl) ? $siteHeaderCtaUrl : url($siteHeaderCtaUrl))
        : '';
@endphp
<body class="header-style-{{ $siteHeaderStyle }} footer-style-{{ $siteFooterStyle }} theme-{{ $siteTheme }}">
<header>
    @if ($siteShowTopbar)
    <div class="topbar"><div class="container topbar-inner">
        <div class="topbar-lead">
            <span class="topbar-text">{{ $siteTopbarText ?: $siteTagline }}</span>
            @if ($siteShowDate)
                <x-date-display class="topbar-date" :icon="true" style="{{ $siteDatePosition === 'center' ? 'margin:0 auto' : ($siteDatePosition === 'right' ? 'margin-left:auto' : '') }}" />
            @endif
        </div>
        <div class="topbar-links">
            @include('layouts.partials.social-links', ['links' => $socialLinks, 'class' => 'topbar-socials'])
            @if ($siteShowLoginMenu)
                @auth
                    <a class="topbar-login" style="display:inline-block;border:1px solid rgba(255,255,255,.45);border-radius:{{ $siteLoginRadius }}px;padding:3px 12px" href="{{ route(auth()->user()->homeRouteName()) }}">ড্যাশবোর্ড</a>
                @else
                    @if ($loginMenu && $loginMenu->rootItems->isNotEmpty())
                        @foreach ($loginMenu->rootItems as $loginItem)
                            <a class="topbar-login" style="display:inline-block;border:1px solid rgba(255,255,255,.45);border-radius:{{ $siteLoginRadius }}px;padding:3px 12px;margin-left:6px" href="{{ $loginItem->href }}" @if ($loginItem->target === '_blank') target="_blank" rel="noopener" @endif>{{ $loginItem->title }}</a>
                        @endforeach
                    @else
                        <a class="topbar-login" style="display:inline-block;border:1px solid rgba(255,255,255,.45);border-radius:{{ $siteLoginRadius }}px;padding:3px 12px" href="{{ route('student.login') }}">Student Login</a>
                        <a class="topbar-login" style="display:inline-block;border:1px solid rgba(255,255,255,.45);border-radius:{{ $siteLoginRadius }}px;padding:3px 12px;margin-left:6px" href="{{ route('login') }}">Staff Login</a>
                    @endif
                @endauth
            @endif
        </div>
    </div></div>
    @endif
    <div class="brand-row"><div class="container brand-inner">
        <a class="logo" href="{{ route('website.home') }}">
            @if ($siteLogoUrl)
                <img class="site-logo" src="{{ $siteLogoUrl }}" alt="{{ $siteNameBn }} logo">
            @else
                <span class="logo-mark">{{ $logoMark }}</span>
            @endif
        </a>
        <div class="brand-copy"><h1>{{ $siteNameBn }}</h1><p>{{ $siteNameEn }}</p></div>
        @if ($siteHeaderCtaLabel && $ctaHref)
            <a class="header-cta" href="{{ $ctaHref }}">{{ $siteHeaderCtaLabel }} <span>→</span></a>
        @endif
    </div></div>
    <div class="main-nav"><div class="container nav-inner">
        <a class="mobile-brand" href="{{ route('website.home') }}"><span class="mini-logo">
            @if ($siteLogoUrl)
                <img src="{{ $siteLogoUrl }}" alt="{{ $siteNameBn }} logo">
            @else
                {{ $logoMark }}
            @endif
        </span><span><strong>{{ $siteNameBn }}</strong><small>{{ $siteNameEn }}</small></span></a>
        <nav class="nav-links">
            @if ($headerMenu && $headerMenu->rootItems->isNotEmpty())
                @foreach ($headerMenu->rootItems as $item)
                    @if ($item->children->isNotEmpty())
                        <div class="has-submenu">
                            <a href="{{ $item->href }}" @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>{{ $item->title }}</a>
                            <ul class="submenu">
                                @foreach ($item->children as $child)
                                    <li><a href="{{ $child->href }}" @if ($child->target === '_blank') target="_blank" rel="noopener" @endif>{{ $child->title }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <a href="{{ $item->href }}" @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>{{ $item->title }}</a>
                    @endif
                @endforeach
            @else
            <a href="{{ route('website.home') }}">প্রচ্ছদ</a>
            <div class="has-submenu"><a href="{{ route('website.about') }}">পরিচিতি</a><ul class="submenu">
                <li><a href="{{ route('website.about') }}">বিদ্যালয় পরিচিতি</a></li>
                <li><a href="{{ route('website.administration-message') }}">প্রশাসনের বাণী</a></li>
                <li><a href="{{ route('website.principal-message') }}">প্রধান শিক্ষকের বাণী</a></li>
                <li><a href="{{ route('website.president-message') }}">সভাপতির বাণী</a></li>
                <li><a href="{{ route('website.about') }}#mission">লক্ষ্য ও উদ্দেশ্য</a></li>
                <li><a href="{{ route('website.about') }}#rules">নিয়মাবলি</a></li>
            </ul></div>
            <div class="has-submenu"><a href="{{ route('website.academics') }}">শিক্ষাক্রম</a><ul class="submenu">
                <li><a href="{{ route('website.academics') }}">একাডেমিক কার্যক্রম</a></li>
                <li><a href="{{ route('website.academics') }}#classes">ক্লাস ও বিষয়</a></li>
                <li><a href="{{ route('website.calendar') }}">একাডেমিক ক্যালেন্ডার</a></li>
                <li><a href="{{ route('website.downloads') }}">সিলেবাস ও ডাউনলোড</a></li>
            </ul></div>
            <div class="has-submenu"><a href="{{ route('website.faculty') }}">শিক্ষকবৃন্দ</a><ul class="submenu">
                <li><a href="{{ route('website.faculty') }}">শিক্ষক তালিকা</a></li>
                <li><a href="{{ route('website.about') }}#facilities">সুবিধাসমূহ</a></li>
            </ul></div>
            <div class="has-submenu"><a href="{{ route('website.admission') }}">ভর্তি</a><ul class="submenu">
                <li><a href="{{ route('website.admission') }}">ভর্তি তথ্য</a></li>
                <li><a href="{{ route('website.admission') }}#process">ভর্তি প্রক্রিয়া</a></li>
                <li><a href="{{ route('website.admission.apply') }}">অনলাইন আবেদন</a></li>
            </ul></div>
            <div class="has-submenu"><a href="{{ route('website.notices') }}">নোটিশ</a><ul class="submenu">
                <li><a href="{{ route('website.notices') }}">সকল নোটিশ</a></li>
                <li><a href="{{ route('website.events') }}">ইভেন্ট</a></li>
            </ul></div>
            <a href="{{ route('website.news') }}">সংবাদ</a>
            <div class="has-submenu"><a href="{{ route('website.gallery') }}">গ্যালারি</a><ul class="submenu">
                <li><a href="{{ route('website.gallery') }}">ফটো গ্যালারি</a></li>
                <li><a href="{{ route('website.events') }}">ইভেন্ট</a></li>
            </ul></div>
            <div class="has-submenu"><a href="{{ route('student.login') }}">শিক্ষার্থী</a><ul class="submenu">
                <li><a href="{{ route('student.login') }}">Student Login</a></li>
                <li><a href="{{ route('parent.login') }}">Parent Login</a></li>
                <li><a href="{{ route('teacher.login') }}">Teacher Login</a></li>
                <li><a href="{{ route('website.admission.apply') }}">Registration</a></li>
                <li><a href="{{ route('login') }}">Staff Login</a></li>
            </ul></div>
            <a href="{{ route('website.contact') }}">যোগাযোগ</a>
            @endif
        </nav>
        <button class="menu-btn" aria-label="মেনু" aria-expanded="false"><span></span><span></span><span></span></button>
    </div></div>
</header>
<div class="drawer-overlay"></div>
<aside class="drawer" aria-hidden="true">
    <div class="drawer-head"><span class="drawer-logo">
        @if ($siteLogoUrl)
            <img src="{{ $siteLogoUrl }}" alt="{{ $siteNameBn }} logo">
        @else
            {{ $logoMark }}
        @endif
    </span><span><strong>{{ $siteNameBn }}</strong><small>{{ $siteNameEn }}</small></span><button class="drawer-close" aria-label="বন্ধ করুন">×</button></div>
    <nav class="drawer-nav">
        @if ($headerMenu && $headerMenu->rootItems->isNotEmpty())
            @foreach ($headerMenu->rootItems as $item)
                <div class="drawer-group">
                    <a href="{{ $item->href }}" @if ($item->target === '_blank') target="_blank" rel="noopener" @endif><small>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</small>{{ $item->title }}</a>
                    @if ($item->children->isNotEmpty())
                        <div class="drawer-submenu">
                            @foreach ($item->children as $child)
                                <a href="{{ $child->href }}" @if ($child->target === '_blank') target="_blank" rel="noopener" @endif>{{ $child->title }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @else
        <a href="{{ route('website.home') }}"><small>01</small>প্রচ্ছদ</a>
        <div class="drawer-group"><a href="{{ route('website.about') }}"><small>02</small>পরিচিতি</a><div class="drawer-submenu"><a href="{{ route('website.about') }}">বিদ্যালয় পরিচিতি</a><a href="{{ route('website.administration-message') }}">প্রশাসনের বাণী</a><a href="{{ route('website.principal-message') }}">প্রধান শিক্ষকের বাণী</a><a href="{{ route('website.president-message') }}">সভাপতির বাণী</a><a href="{{ route('website.about') }}#mission">লক্ষ্য ও উদ্দেশ্য</a><a href="{{ route('website.about') }}#rules">নিয়মাবলি</a></div></div>
        <div class="drawer-group"><a href="{{ route('website.academics') }}"><small>03</small>শিক্ষাক্রম</a><div class="drawer-submenu"><a href="{{ route('website.academics') }}">একাডেমিক কার্যক্রম</a><a href="{{ route('website.academics') }}#classes">ক্লাস ও বিষয়</a><a href="{{ route('website.calendar') }}">ক্যালেন্ডার</a><a href="{{ route('website.downloads') }}">ডাউনলোড</a></div></div>
        <div class="drawer-group"><a href="{{ route('website.faculty') }}"><small>04</small>শিক্ষকবৃন্দ</a><div class="drawer-submenu"><a href="{{ route('website.faculty') }}">শিক্ষক তালিকা</a><a href="{{ route('website.about') }}#facilities">সুবিধাসমূহ</a></div></div>
        <div class="drawer-group"><a href="{{ route('website.admission') }}"><small>05</small>ভর্তি</a><div class="drawer-submenu"><a href="{{ route('website.admission') }}">ভর্তি তথ্য</a><a href="{{ route('website.admission') }}#process">ভর্তি প্রক্রিয়া</a><a href="{{ route('website.admission.apply') }}">অনলাইন আবেদন</a></div></div>
        <div class="drawer-group"><a href="{{ route('website.notices') }}"><small>06</small>নোটিশ</a><div class="drawer-submenu"><a href="{{ route('website.notices') }}">সকল নোটিশ</a><a href="{{ route('website.events') }}">ইভেন্ট</a></div></div>
        <a href="{{ route('website.news') }}"><small>07</small>সংবাদ</a>
        <div class="drawer-group"><a href="{{ route('website.gallery') }}"><small>08</small>গ্যালারি</a><div class="drawer-submenu"><a href="{{ route('website.gallery') }}">ফটো গ্যালারি</a><a href="{{ route('website.events') }}">ইভেন্ট</a></div></div>
        <div class="drawer-group"><a href="{{ route('student.login') }}"><small>09</small>শিক্ষার্থী</a><div class="drawer-submenu"><a href="{{ route('student.login') }}">Student Login</a><a href="{{ route('parent.login') }}">Parent Login</a><a href="{{ route('teacher.login') }}">Teacher Login</a><a href="{{ route('website.admission.apply') }}">Registration</a></div></div>
        <a href="{{ route('website.contact') }}"><small>10</small>যোগাযোগ</a>
        @endif
    </nav>
    <div class="drawer-cta"><small>ভর্তি ও সাধারণ তথ্য</small><a href="{{ route('website.contact') }}">বিদ্যালয়ের সাথে যোগাযোগ করুন →</a></div>
</aside>

<main>
    @if (session('success'))
        <div class="container" style="padding-top:16px">
            <div style="background:#e8f7eb;border:1px solid #b7e2c0;color:#28723a;padding:14px 16px;border-radius:10px;font-size:15px">{{ session('success') }}</div>
        </div>
    @endif
    @if ($errors->any())
        <div class="container" style="padding-top:16px">
            <div style="background:#fdeaea;border:1px solid #f3c2c2;color:#a83a3a;padding:14px 16px;border-radius:10px;font-size:14px">
                <strong>অনুগ্রহ করে ত্রুটি সংশোধন করুন:</strong>
                <ul style="margin:8px 0 0 18px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    @yield('content')
</main>

<footer class="footer">
    <div class="container footer-main">
        <div class="footer-about">
            <div class="footer-brand">
                <span class="logo">
                    @if ($siteLogoUrl)
                        <img class="site-logo" src="{{ $siteLogoUrl }}" alt="{{ $siteNameBn }} logo">
                    @else
                        <span class="logo-mark">{{ $logoMark }}</span>
                    @endif
                </span>
                <div><h3>{{ $siteNameBn }}</h3><p>{{ $siteTagline }}</p></div>
            </div>
            @if ($siteFooterAbout)<p class="footer-about-text">{{ $siteFooterAbout }}</p>@endif
        </div>

        {{-- Column 2: প্রতিষ্ঠান --}}
        @php
            $institutionItems = ($footerInstitutionMenu && $footerInstitutionMenu->rootItems->isNotEmpty())
                ? $footerInstitutionMenu->rootItems
                : (($footerMenu && $footerMenu->rootItems->isNotEmpty()) ? $footerMenu->rootItems : null);
        @endphp
        @if ($institutionItems)
            <div>
                <h3>{{ $siteFooterInstitutionTitle }}</h3>
                @foreach ($institutionItems as $item)
                    <a href="{{ $item->href }}" @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>{{ $item->title }}</a>
                    @foreach ($item->children as $child)
                        <a href="{{ $child->href }}" style="padding-left:12px" @if ($child->target === '_blank') target="_blank" rel="noopener" @endif>{{ $child->title }}</a>
                    @endforeach
                @endforeach
            </div>
        @else
            <div><h3>{{ $siteFooterInstitutionTitle }}</h3><a href="{{ route('website.about') }}">বিদ্যালয় পরিচিতি</a><a href="{{ route('website.faculty') }}">শিক্ষকবৃন্দ</a><a href="{{ route('website.about') }}#facilities">সুবিধাসমূহ</a><a href="{{ route('website.gallery') }}">ফটো গ্যালারি</a></div>
        @endif

        {{-- Column 3: গুরুত্বপূর্ণ লিংক --}}
        @if ($footerLinksMenu && $footerLinksMenu->rootItems->isNotEmpty())
            <div>
                <h3>{{ $siteFooterLinksTitle }}</h3>
                @foreach ($footerLinksMenu->rootItems as $item)
                    <a href="{{ $item->href }}" @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>{{ $item->title }}</a>
                    @foreach ($item->children as $child)
                        <a href="{{ $child->href }}" style="padding-left:12px" @if ($child->target === '_blank') target="_blank" rel="noopener" @endif>{{ $child->title }}</a>
                    @endforeach
                @endforeach
            </div>
        @else
            <div><h3>{{ $siteFooterLinksTitle }}</h3><a href="{{ route('website.notices') }}">নোটিশ বোর্ড</a><a href="{{ route('website.calendar') }}">একাডেমিক ক্যালেন্ডার</a><a href="{{ route('website.downloads') }}">ডাউনলোড</a><a href="{{ route('website.admission.apply') }}">ভর্তি আবেদন</a></div>
        @endif

        {{-- Column 4: যোগাযোগ --}}
        @php
            $footerAddress = $siteFooterContactAddress ?: $siteAddress;
            $footerEmail = $siteFooterContactEmail ?: $siteEmail;
            $footerPhone = $siteFooterContactPhone ?: $sitePhone;
        @endphp
        <div>
            <h3>যোগাযোগ</h3>
            @if ($footerAddress)<p>{{ $footerAddress }}</p>@endif
            @if ($footerPhone)<p>ফোন: {{ $footerPhone }}</p>@endif
            @if ($footerEmail)<p>ইমেইল: {{ $footerEmail }}</p>@endif
            <p><a href="{{ route('website.contact') }}" style="padding:0">যোগাযোগ ফর্ম →</a></p>
            @include('layouts.partials.social-links', ['links' => $socialLinks, 'class' => 'footer-social'])
        </div>
    </div>
    <div class="footer-bottom"><div class="container" style="display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap">
        <span>{{ $siteFooterCopyright ? str_replace(':year', now()->format('Y'), $siteFooterCopyright) : '© '.now()->format('Y').' '.$siteNameBn.' • সর্বস্বত্ব সংরক্ষিত' }}</span>
        <span>@if ($siteFooterNote){{ $siteFooterNote }} · @endif<a href="{{ route('website.privacy') }}" style="display:inline;padding:0">গোপনীয়তা নীতি</a></span>
    </div></div>
</footer>

<script src="{{ asset('website/script.js') }}?v={{ @filemtime(public_path('website/script.js')) ?: '1' }}"></script>
@stack('scripts')
</body>
</html>
