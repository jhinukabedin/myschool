{{-- Compact Android-style bottom navigation, shown on phones and small tablets only. --}}
<nav class="mobile-nav" aria-label="{{ __('Main navigation') }}">
    @can('dashboard.view')
        <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="mobile-nav-icon"><x-icon name="layout-dashboard" :size="20" /></span>
            <span class="mobile-nav-label">{{ __('Dashboard') }}</span>
        </a>
    @endcan

    @if (feature_enabled('student-records'))
        @can('students.view')
            <a href="{{ route('students.index') }}" class="mobile-nav-item {{ request()->routeIs('students.*', 'admissions.*', 'enquiries.*') ? 'active' : '' }}">
                <span class="mobile-nav-icon"><x-icon name="users" :size="20" /></span>
                <span class="mobile-nav-label">{{ __('Students') }}</span>
            </a>
        @endcan
    @endif

    @if (feature_enabled('fee-collection'))
        @can('fees.collect')
            <a href="{{ route('fees.collection.index') }}" class="mobile-nav-item {{ request()->routeIs('fees.collection.*', 'fees.quick.*') ? 'active' : '' }}">
                <span class="mobile-nav-icon"><x-icon name="circle-dollar" :size="20" /></span>
                <span class="mobile-nav-label">{{ __('Fees') }}</span>
            </a>
        @endcan
    @endif

    @can('reports.view')
        <a href="{{ route('reports.index') }}" class="mobile-nav-item {{ request()->routeIs('reports.*', 'session-report.*') ? 'active' : '' }}">
            <span class="mobile-nav-icon"><x-icon name="pie-chart" :size="20" /></span>
            <span class="mobile-nav-label">{{ __('Reports') }}</span>
        </a>
    @endcan

    <button type="button" class="mobile-nav-item" @click="sidebarOpen = true" aria-label="{{ __('Open navigation') }}">
        <span class="mobile-nav-icon"><x-icon name="menu" :size="20" /></span>
        <span class="mobile-nav-label">{{ __('Menu') }}</span>
    </button>
</nav>
