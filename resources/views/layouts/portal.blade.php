@php
    $portalUser = auth()->user();
    $portalRole = $portalUser?->role?->slug;
    $isStaffPortal = request()->routeIs('staff-portal.*');
    $portalName = $isStaffPortal ? 'Staff Portal' : match ($portalRole) {
        'student' => 'Student Portal',
        'parent' => 'Parent Portal',
        'teacher' => 'Teacher Portal',
        default => 'School Portal',
    };
    $isStudent = ! $isStaffPortal && $portalRole === 'student';
    $portalNav = $isStaffPortal ? [
        ['label' => 'Dashboard', 'route' => 'staff-portal.dashboard', 'active' => 'staff-portal.dashboard'],
        ['label' => 'My Profile', 'route' => 'staff-portal.profile', 'active' => 'staff-portal.profile'],
        ['label' => 'Leave', 'route' => 'staff-portal.leaves.index', 'active' => 'staff-portal.leaves.*'],
        ['label' => 'Payslips', 'route' => 'staff-portal.payslips.index', 'active' => 'staff-portal.payslips.*'],
        ['label' => 'Payment History', 'route' => 'staff-portal.payments.index', 'active' => 'staff-portal.payments.*'],
        ['label' => 'Change Password', 'route' => 'password.change', 'active' => 'password.*'],
    ] : match ($portalRole) {
        'student' => [
            ['label' => 'Dashboard', 'route' => 'student.dashboard', 'active' => 'student.dashboard'],
            ['label' => 'My Profile', 'route' => 'student.profile', 'active' => 'student.profile'],
            ['label' => 'Results', 'route' => 'student.results', 'active' => 'student.results*'],
            ['label' => 'Class Routine', 'route' => 'student.routine', 'active' => 'student.routine'],
            ['label' => 'Attendance', 'route' => 'student.attendance', 'active' => 'student.attendance'],
            ['label' => 'Assignments', 'route' => 'student.assignments', 'active' => 'student.assignments'],
            ['label' => 'Study Materials', 'route' => 'student.materials', 'active' => 'student.materials'],
            ['label' => 'Notice Board', 'route' => 'student.notices', 'active' => 'student.notices*'],
            ['label' => 'Academic Calendar', 'route' => 'student.calendar', 'active' => 'student.calendar'],
            ['label' => 'Fees & Payments', 'route' => 'student.fees', 'active' => 'student.fees|student.payments.*'],
            ['label' => 'Downloads', 'route' => 'student.downloads', 'active' => 'student.downloads'],
            ['label' => 'Change Password', 'route' => 'password.change', 'active' => 'password.*'],
        ],
        'parent' => [
            ['label' => 'Dashboard', 'route' => 'parent.dashboard', 'active' => 'parent.dashboard'],
            ['label' => 'My Profile', 'route' => 'parent.profile', 'active' => 'parent.profile'],
            ['label' => 'Password', 'route' => 'password.change', 'active' => 'password.*'],
        ],
        'teacher' => [
            ['label' => 'My Teaching', 'route' => 'teacher.dashboard', 'active' => 'teacher.dashboard'],
            ['label' => 'My Class Schedule', 'route' => 'teacher.schedule', 'active' => 'teacher.schedule'],
            ['label' => 'Marks Entry', 'route' => 'exams.marks-entry', 'active' => 'exams.marks-entry'],
            ['label' => 'Attendance', 'route' => 'attendance.index', 'active' => 'attendance.index'],
            ['label' => 'Exam Schedule', 'route' => 'exams.schedule', 'active' => 'exams.schedule'],
            ['label' => 'Password', 'route' => 'password.change', 'active' => 'password.*'],
        ],
        default => [],
    };

    $sidebarStudent = $isStudent
        ? $portalUser?->studentPortalLink()->active()->with('student')->first()?->student
        : null;
    $sidebarInitials = $sidebarStudent
        ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($sidebarStudent->first_name, 0, 1).\Illuminate\Support\Str::substr($sidebarStudent->last_name, 0, 1))
        : \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $portalUser?->name, 0, 2));
    $sidebarId = $sidebarStudent?->student_code ?: $sidebarStudent?->admission_no;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $portalName) · {{ school_setting('website_name_en', 'Adarsha High School') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Bengali:wght@400;500;600;700&family=Noto+Serif+Bengali:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('portal/style.css') }}">
    <style>
        .report-print-footer { display:none; }
        @media print {
            @page { margin-bottom:18mm; }
            .report-print-footer { position:fixed; right:0; bottom:0; left:0; z-index:1000; display:flex !important; justify-content:space-between; align-items:center; gap:12px; width:100%; padding:5px 0 0; border-top:1px solid #cbd1dc; background:#fff; color:#647083; font-size:8pt; line-height:1.35; }
            .report-print-footer strong { color:#27354a; }
            .report-print-footer a { color:#425c80; text-decoration:none; }
            .report-print-footer-contact { display:flex; gap:10px; }
            .report-print-footer-pages::after { content:"Page " counter(page) " of " counter(pages); }
        }
    </style>
    @stack('head')
</head>
<body class="{{ $isStudent ? 'portal-student' : 'portal-classic' }}">
<a class="skip-link" href="#portal-main">Skip to content</a>
<header class="portal-header">
    <div class="portal-wrap header-inner">
        <a class="portal-brand" href="{{ route($portalUser?->homeRouteName() ?? 'website.home') }}">
            <span class="brand-mark">{{ school_setting('website_logo_mark', 'HS') }}</span>
            <span>
                <strong>{{ school_setting('website_name_bn', 'আদর্শ উচ্চ বিদ্যালয়') }}</strong>
                <small>{{ $portalName }}</small>
            </span>
        </a>
        <div class="header-meta">
            <x-date-display class="date-pill" :icon="true" />
            <span class="session-pill">Session {{ current_session()?->name ?? '—' }}</span>
            <span class="user-pill">{{ $portalUser?->name }}<small>{{ $portalName }}</small></span>
            <a class="btn btn-ghost btn-sm portal-site-link" href="{{ route('website.home') }}">School website ↗</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost">Sign out</button>
            </form>
        </div>
    </div>
</header>

@if ($isStudent)
    <section class="portal-banner">
        <div class="portal-wrap">
            <div class="breadcrumb">{{ $portalName }} / @yield('title', 'Dashboard')</div>
            <h1>@yield('title', 'Dashboard')</h1>
            @hasSection('banner-subtitle')
                <p>@yield('banner-subtitle')</p>
            @endif
        </div>
    </section>

    <div class="portal-wrap portal-shell">
        <aside class="portal-sidebar" aria-label="Student portal navigation">
            <div class="user">
                <div class="portal-avatar">
                    @if ($sidebarStudent?->photo_url)
                        <img src="{{ $sidebarStudent->photo_url }}" alt="{{ $sidebarStudent->full_name }}">
                    @else
                        {{ $sidebarInitials }}
                    @endif
                </div>
                <b>{{ $sidebarStudent?->full_name ?? $portalUser?->name }}</b>
                <span>Student ID: {{ $sidebarId ?? '—' }}</span>
            </div>

            @foreach ($portalNav as $item)
                <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs($item['active'])])>{{ $item['label'] }}</a>
            @endforeach

            <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </aside>

        <main id="portal-main" class="portal-content content-area">
            @include('portal.partials.alerts')
            @yield('content')
        </main>
    </div>
@else
    <nav class="portal-nav" aria-label="Portal navigation">
        <div class="portal-wrap nav-inner">
            @foreach ($portalNav as $item)
                <a href="{{ route($item['route']) }}" @class(['nav-link', 'active' => request()->routeIs($item['active'])])>{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ route('website.home') }}" class="nav-link nav-link-external">School website ↗</a>
        </div>
    </nav>

    <main id="portal-main" class="portal-wrap portal-main">
        @include('portal.partials.alerts')
        @yield('content')
    </main>
@endif

<footer class="portal-footer">
    <div class="portal-wrap footer-inner">
        <span>© {{ now()->format('Y') }} {{ school_setting('school_name', 'School') }}. All rights reserved.</span>
        <a href="{{ route('website.privacy') }}">Privacy</a>
    </div>
</footer>
@include('layouts.partials.print-developer-footer')
@stack('scripts')
</body>
</html>
