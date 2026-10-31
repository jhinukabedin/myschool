@php
    $user = auth()->user();
    $groups = [
        [
            'label' => 'Student Management',
            'items' => [
                ['title' => 'Admission Enquiry', 'icon' => 'message-circle', 'route' => 'enquiries.index', 'pattern' => 'enquiries.*', 'can' => 'enquiries.view'],
                ['title' => 'New Admission', 'icon' => 'user-plus', 'route' => 'admissions.create', 'pattern' => 'admissions.*', 'can' => 'students.create'],
                ['title' => 'All Students', 'icon' => 'users', 'route' => 'students.index', 'pattern' => 'students.index|students.show|students.edit', 'can' => 'students.view'],
                ['title' => 'Promotion', 'icon' => 'trending-up', 'route' => 'promotion.index', 'pattern' => 'promotion.*', 'can' => 'students.promote'],
                ['title' => 'Passed Out Archive', 'icon' => 'archive', 'route' => 'students.archive', 'pattern' => 'students.archive', 'can' => 'students.view'],
                ['title' => 'Session Report', 'icon' => 'file-bar-chart', 'route' => 'session-report.index', 'pattern' => 'session-report.*', 'can' => 'reports.view'],
            ],
        ],
        [
            'label' => 'Academic Management',
            'items' => [
                ['title' => 'My Teaching', 'icon' => 'book-open', 'route' => 'teacher.dashboard', 'pattern' => 'teacher.dashboard', 'can' => 'marks.enter'],
                ['title' => 'Subjects', 'icon' => 'book-open', 'route' => 'subjects.index', 'pattern' => 'subjects.*', 'can' => 'academics.view'],
                ['title' => 'Class-Subject Map', 'icon' => 'list-checks', 'route' => 'class-subjects.index', 'pattern' => 'class-subjects.*', 'can' => 'academics.view'],
                ['title' => 'Teacher Assignments', 'icon' => 'users', 'route' => 'assignments.index', 'pattern' => 'assignments.*', 'can' => 'assignments.view'],
                ['title' => 'Class Routine', 'icon' => 'clipboard-list', 'route' => 'routines.index', 'pattern' => 'routines.*', 'can' => 'routines.view'],
            ],
        ],
        [
            'label' => 'Attendance Management',
            'items' => [
                ['title' => 'Attendance', 'icon' => 'calendar-check', 'route' => 'attendance.index', 'pattern' => 'attendance.index', 'can' => 'attendance.view'],
                ['title' => 'Attendance Report', 'icon' => 'clipboard-list', 'route' => 'attendance.report', 'pattern' => 'attendance.report', 'can' => 'attendance.report'],
            ],
        ],
        [
            'label' => 'Examination & Results',
            'items' => [
                ['title' => 'Exams', 'icon' => 'graduation-cap', 'route' => 'exams.index', 'pattern' => 'exams.index|exams.create|exams.edit|exams.show', 'can' => 'exams.view'],
                ['title' => 'Exam Schedule', 'icon' => 'calendar', 'route' => 'exams.schedule', 'pattern' => 'exams.schedule', 'can' => 'exams.view'],
                ['title' => 'Marks Entry', 'icon' => 'pencil', 'route' => 'exams.marks-entry', 'pattern' => 'exams.marks-entry|exams.marks-store', 'can' => 'marks.enter'],
                ['title' => 'Class Result', 'icon' => 'file-bar-chart', 'route' => 'exams.class-result', 'pattern' => 'exams.class-result|exams.result-sheet', 'can' => 'results.view'],
            ],
        ],
        [
            'label' => 'Fees & Payments',
            'items' => [
                ['title' => 'Fee Structure', 'icon' => 'wallet', 'route' => 'fees.structures.index', 'pattern' => 'fees.structures.*', 'can' => 'fees.view'],
                ['title' => 'Fee Collection', 'icon' => 'receipt', 'route' => 'fees.collection.index', 'pattern' => 'fees.collection.*', 'can' => 'fees.collect'],
                ['title' => 'Quick Fee Collect', 'icon' => 'zap', 'route' => 'fees.quick.index', 'pattern' => 'fees.quick.*', 'can' => 'fees.collect'],
                ['title' => 'WhatsApp Reminder', 'icon' => 'message-circle', 'route' => 'fees.reminders.index', 'pattern' => 'fees.reminders.*', 'can' => 'fees.view'],
                ['title' => 'Fee Deletion Log', 'icon' => 'trash', 'route' => 'fees.deletion-log', 'pattern' => 'fees.deletion-log', 'can' => 'fees.view'],
            ],
        ],
        [
            'label' => 'Finance & Accounts',
            'items' => [
                ['title' => 'Expenses', 'icon' => 'credit-card', 'route' => 'expenses.index', 'pattern' => 'expenses.*', 'can' => 'finance.view'],
                ['title' => 'Income vs Expense', 'icon' => 'trending-down', 'route' => ['reports.show', 'income-expense'], 'pattern' => 'reports.show', 'can' => 'reports.view'],
                ['title' => 'All Reports', 'icon' => 'pie-chart', 'route' => 'reports.index', 'pattern' => 'reports.index', 'can' => 'reports.view'],
            ],
        ],
        [
            'label' => 'Staff & Payroll',
            'items' => [
                ['title' => 'Staff Management', 'icon' => 'briefcase', 'route' => 'staff.index', 'pattern' => 'staff.*', 'can' => 'staff.view'],
                ['title' => 'Salary Management', 'icon' => 'banknote', 'route' => 'payroll.index', 'pattern' => 'payroll.index|payroll.generate|payroll.process|payroll.pay|payroll.slip', 'can' => 'payroll.view'],
                ['title' => 'Salary Report', 'icon' => 'file-text', 'route' => 'payroll.report', 'pattern' => 'payroll.report', 'can' => 'payroll.view'],
            ],
        ],
        [
            'label' => 'Communication',
            'items' => [
                ['title' => 'Notices', 'icon' => 'bell', 'route' => 'notices.index', 'pattern' => 'notices.*', 'can' => 'notices.view'],
            ],
        ],
        [
            'label' => 'Website Management',
            'items' => [
                ['title' => 'Website Dashboard', 'icon' => 'layout-dashboard', 'route' => 'website-admin.index', 'pattern' => 'website-admin.index', 'can' => 'website.view'],
                ['title' => 'Header Area', 'icon' => 'layout', 'route' => 'website-admin.header', 'pattern' => 'website-admin.header', 'can' => 'website.view'],
                ['title' => 'Footer Area', 'icon' => 'layout', 'route' => 'website-admin.footer', 'pattern' => 'website-admin.footer', 'can' => 'website.view'],
                ['title' => 'Hero Section', 'icon' => 'layout', 'route' => 'website-admin.hero', 'pattern' => 'website-admin.hero|website-admin.sliders|website-admin.featured-menu', 'can' => 'website.view'],
                ['title' => 'Institution Introduction', 'icon' => 'message-circle', 'route' => 'website-admin.introduction', 'pattern' => 'website-admin.introduction', 'can' => 'website.view'],
                ['title' => 'Notice', 'icon' => 'bell', 'route' => 'website-admin.notice', 'pattern' => 'website-admin.notice', 'can' => 'website.view'],
                ['title' => 'Message of Management', 'icon' => 'user', 'route' => 'website-admin.administration-message', 'pattern' => 'website-admin.administration-message|website-admin.principal-message|website-admin.president-message', 'can' => 'website.view'],
                ['title' => 'Academic Corner', 'icon' => 'graduation-cap', 'route' => 'website-admin.academic-corner', 'pattern' => 'website-admin.academic-corner', 'can' => 'website.view'],
                ['title' => 'Admission Info', 'icon' => 'user-plus', 'route' => 'website-admin.admission-info', 'pattern' => 'website-admin.admission-info', 'can' => 'website.view'],
                ['title' => 'Class Routine', 'icon' => 'list', 'route' => 'website-admin.class-routine', 'pattern' => 'website-admin.class-routine', 'can' => 'website.view'],
                ['title' => 'Academic Calendar', 'icon' => 'calendar', 'route' => 'website-admin.academic-calendar', 'pattern' => 'website-admin.academic-calendar', 'can' => 'website.view'],
                ['title' => 'Syllabus', 'icon' => 'file-text', 'route' => 'website-admin.syllabus', 'pattern' => 'website-admin.syllabus', 'can' => 'website.view'],
                ['title' => 'Assignments', 'icon' => 'clipboard-list', 'route' => 'website-admin.assignments', 'pattern' => 'website-admin.assignments', 'can' => 'website.view'],
                ['title' => 'News & Event', 'icon' => 'file-text', 'route' => 'website-admin.news', 'pattern' => 'website-admin.news|website-admin.events', 'can' => 'website.view'],
                ['title' => 'Image Gallery', 'icon' => 'eye', 'route' => 'website-admin.gallery', 'pattern' => 'website-admin.gallery', 'can' => 'website.view'],
                ['title' => 'Video Gallery', 'icon' => 'video', 'route' => 'website-admin.video-gallery', 'pattern' => 'website-admin.video-gallery', 'can' => 'website.view'],
                ['title' => 'Downloads', 'icon' => 'download', 'route' => 'website-admin.downloads', 'pattern' => 'website-admin.downloads', 'can' => 'website.view'],
                ['title' => 'Content Library', 'icon' => 'book-open', 'route' => 'website-content.index', 'pattern' => 'website-content.index', 'can' => 'website.view'],
                ['title' => 'Menus', 'icon' => 'menu', 'route' => 'website-menus.index', 'pattern' => 'website-menus.*', 'can' => 'website.menus'],
                ['title' => 'Media', 'icon' => 'eye', 'route' => 'media.index', 'pattern' => 'media.*', 'can' => 'website.media'],
                ['title' => 'Create New Page', 'icon' => 'plus', 'route' => 'website-admin.create-page', 'pattern' => 'website-admin.create-page', 'can' => 'website.view'],
                ['title' => 'Sidebar', 'icon' => 'layout', 'route' => 'website-admin.sidebar', 'pattern' => 'website-admin.sidebar', 'can' => 'website.view'],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['title' => 'Admin Settings', 'icon' => 'user-cog', 'route' => 'admin.index', 'pattern' => 'admin.*|sessions.*|classes.*|sections.*|users.*|roles.*|audit.*|portal-accounts.*', 'can' => 'settings.view'],
                ['title' => 'Portal Accounts', 'icon' => 'user-check', 'route' => 'portal-accounts.index', 'pattern' => 'portal-accounts.*', 'can' => 'portals.manage'],
                ['title' => 'Settings', 'icon' => 'settings', 'route' => 'settings.index', 'pattern' => 'settings.*', 'can' => 'settings.view'],
            ],
        ],
    ];

    // Keep the group that owns the current page expanded after every navigation.
    $activeGroup = null;
    foreach ($groups as $index => $group) {
        foreach ($group['items'] as $item) {
            if ($user?->can($item['can']) && collect(explode('|', $item['pattern']))->contains(fn ($pattern) => request()->routeIs($pattern))) {
                $activeGroup = $index;
                break 2;
            }
        }
    }

    $openGroup = request()->routeIs('password.*') ? 'account' : $activeGroup;
@endphp

<aside class="sidebar" id="app-sidebar" :class="sidebarOpen && 'open'" aria-label="Main navigation">
    <div class="sidebar-brand">
        <div class="brand-mark"><x-icon name="graduation-cap" :size="23" /></div>
        <div>
            <div class="brand-name">{{ $schoolSettings['name'] ?? 'EduSphere' }}</div>
            <div class="brand-sub">School Management</div>
        </div>
        <button type="button" class="btn-icon" style="margin-left:auto" @click="sidebarOpen = false" aria-label="Close navigation">
            <x-icon name="x" :size="17" />
        </button>
    </div>

    <div class="sidebar-scroll" x-data="{ openGroup: @js($openGroup) }">
        @can('dashboard.view')
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <x-icon name="layout-dashboard" :size="17" class="nav-icon" />
                <span>Dashboard</span>
            </a>
        @endcan

        @foreach ($groups as $group)
            @php
                $visible = collect($group['items'])->filter(fn ($item) => $user?->can($item['can']))->count();
            @endphp
            @if ($visible)
                <div class="nav-section">
                    <button type="button" class="nav-label nav-toggle" x-on:click="openGroup = openGroup === {{ $loop->index }} ? null : {{ $loop->index }}" x-bind:aria-expanded="openGroup === {{ $loop->index }}" aria-controls="nav-group-{{ $loop->index }}">
                        <span>{{ $group['label'] }}</span>
                        <x-icon name="chevron-down" :size="14" class="nav-chevron" x-bind:class="{ 'is-open': openGroup === {{ $loop->index }} }" />
                    </button>
                    <div id="nav-group-{{ $loop->index }}" x-show="openGroup === {{ $loop->index }}" x-cloak>
                        @foreach ($group['items'] as $item)
                            @can($item['can'])
                                @php
                                    $url = is_array($item['route']) ? route($item['route'][0], $item['route'][1]) : route($item['route']);
                                    $active = collect(explode('|', $item['pattern']))->contains(fn ($pattern) => request()->routeIs($pattern));
                                @endphp
                                <a href="{{ $url }}" class="sidebar-link {{ $active ? 'active' : '' }}">
                                    <x-icon :name="$item['icon']" :size="17" class="nav-icon" />
                                    <span>{{ $item['title'] }}</span>
                                </a>
                            @endcan
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="nav-section">
            <button type="button" class="nav-label nav-toggle" x-on:click="openGroup = openGroup === 'account' ? null : 'account'" x-bind:aria-expanded="openGroup === 'account'" aria-controls="nav-group-account">
                <span>My Account</span>
                <x-icon name="chevron-down" :size="14" class="nav-chevron" x-bind:class="{ 'is-open': openGroup === 'account' }" />
            </button>
            <div id="nav-group-account" x-show="openGroup === 'account'" x-cloak>
                <a href="{{ route('password.change') }}" class="sidebar-link {{ request()->routeIs('password.*') ? 'active' : '' }}">
                    <x-icon name="key" :size="17" class="nav-icon" />
                    <span>Password Change</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sidebar-link" style="width:100%;border:0;background:transparent;text-align:left">
                        <x-icon name="log-out" :size="17" class="nav-icon" />
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="session-chip">
            <span class="dot"></span>
            <div>
                <div class="session-chip-label">Academic Session</div>
                <div class="session-chip-value">{{ $currentSession?->name ?? 'Not configured' }}</div>
            </div>
            <x-icon name="chevron-right" :size="14" style="margin-left:auto;color:#7c6f9e" />
        </div>
    </div>
</aside>
