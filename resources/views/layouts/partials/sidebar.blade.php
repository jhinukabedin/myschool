@php
    $user = auth()->user();
    $groups = \App\Support\Navigation::groups();

    $moduleAccess = app(\App\Services\ModuleAccess::class);

    $itemIsActive = function (array $item): bool {
        if (! collect(explode('|', $item['pattern']))->contains(fn ($pattern) => request()->routeIs($pattern))) {
            return false;
        }

        return true;
    };

    // Keep the group that owns the current page expanded after every navigation.
    $activeGroup = null;
    foreach ($groups as $index => $group) {
        foreach ($group['items'] as $item) {
            if ($user?->can($item['can']) && $itemIsActive($item)) {
                $activeGroup = $index;
                break 2;
            }
        }
    }

    $openGroup = request()->routeIs('password.*') ? 'account' : $activeGroup;
@endphp

<aside class="sidebar" id="app-sidebar" :class="sidebarOpen && 'open'" aria-label="{{ __('Main navigation') }}">
    <div class="sidebar-brand">
        <div class="brand-mark"><x-icon name="graduation-cap" :size="23" /></div>
        <div>
            <div class="brand-name">{{ $schoolSettings['name'] ?? 'EduSphere' }}</div>
            <div class="brand-sub">{{ $schoolSettings['shift'] ?? 'Morning Shift' }}</div>
        </div>
        <button type="button" class="btn-icon" style="margin-left:auto" @click="sidebarOpen = false" aria-label="{{ __('Close navigation') }}">
            <x-icon name="x" :size="17" />
        </button>
    </div>

    <div class="sidebar-scroll" x-data="{ openGroup: @js($openGroup) }">
        @can('dashboard.view')
            <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <x-icon name="layout-dashboard" :size="17" class="nav-icon" />
                <span>{{ __('Dashboard') }}</span>
            </a>
        @endcan

        @foreach ($groups as $group)
            @php
                $visible = collect($group['items'])->filter(fn ($item) => $user?->can($item['can']))->count();
            @endphp
            @if ($visible)
                <div class="nav-section">
                    <button type="button" class="nav-label nav-toggle" x-on:click="openGroup = openGroup === {{ $loop->index }} ? null : {{ $loop->index }}" x-bind:aria-expanded="openGroup === {{ $loop->index }}" aria-controls="nav-group-{{ $loop->index }}">
                        <span>{{ __($group['label']) }}</span>
                        <x-icon name="chevron-down" :size="14" class="nav-chevron" x-bind:class="{ 'is-open': openGroup === {{ $loop->index }} }" />
                    </button>
                    <div id="nav-group-{{ $loop->index }}" x-show="openGroup === {{ $loop->index }}" x-cloak>
                        @foreach ($group['items'] as $item)
                            @can($item['can'])
                                @php
                                    $url = is_array($item['route']) ? route($item['route'][0], $item['route'][1]) : route($item['route']);
                                    $active = $itemIsActive($item);
                                    $routeName = is_array($item['route']) ? $item['route'][0] : $item['route'];
                                    $menu = $moduleAccess->menuForRoute($routeName);
                                    $moduleEnabled = $moduleAccess->canAccessRoute($user, $routeName);
                                    $featureLabel = $menu['title'] ?? $moduleAccess->featureLabel($moduleAccess->featureForRoute($routeName));
                                    $menuOn = $user?->isSuperAdmin() || ! $menu || $moduleAccess->menuEnabled($menu['key']);
                                @endphp
                                @if ($moduleEnabled)
                                    <a href="{{ $url }}" class="sidebar-link {{ $active ? 'active' : '' }}">
                                        <x-icon :name="$item['icon']" :size="17" class="nav-icon" />
                                        <span>{{ __($item['title']) }}</span>
                                    </a>
                                @else
                                    {{-- Super admin switched it off or the module is locked: stay visible but disabled. --}}
                                    <span class="sidebar-link" style="opacity:.55;cursor:not-allowed"
                                          title="{{ $menuOn ? __(':feature is locked. Contact the super administrator to unlock it.', ['feature' => $featureLabel]) : __(':feature is turned off by the super administrator.', ['feature' => $featureLabel]) }}"
                                          aria-disabled="true">
                                        <x-icon :name="$item['icon']" :size="17" class="nav-icon" />
                                        <span>{{ __($item['title']) }}</span>
                                        <x-icon name="lock" :size="13" style="margin-left:auto;color:#c9a227" />
                                    </span>
                                @endif
                            @endcan
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div class="nav-section">
            <button type="button" class="nav-label nav-toggle" x-on:click="openGroup = openGroup === 'account' ? null : 'account'" x-bind:aria-expanded="openGroup === 'account'" aria-controls="nav-group-account">
                <span>{{ __('My Account') }}</span>
                <x-icon name="chevron-down" :size="14" class="nav-chevron" x-bind:class="{ 'is-open': openGroup === 'account' }" />
            </button>
            <div id="nav-group-account" x-show="openGroup === 'account'" x-cloak>
                @if (auth()->user()?->staff)
                    <a href="{{ route('staff-portal.dashboard') }}" class="sidebar-link {{ request()->routeIs('staff-portal.*') ? 'active' : '' }}">
                        <x-icon name="briefcase" :size="17" class="nav-icon" />
                        <span>{{ __('Staff Portal') }}</span>
                    </a>
                @endif
                <a href="{{ route('password.change') }}" class="sidebar-link {{ request()->routeIs('password.*') ? 'active' : '' }}">
                    <x-icon name="key" :size="17" class="nav-icon" />
                    <span>{{ __('Password Change') }}</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                    @csrf
                    <button type="submit" class="sidebar-link" style="width:100%;border:0;background:transparent;text-align:left">
                        <x-icon name="log-out" :size="17" class="nav-icon" />
                        <span>{{ __('Logout') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="sidebar-footer">
        <div class="session-chip">
            <span class="dot"></span>
            <div>
                <div class="session-chip-label">{{ __('Academic Session') }}</div>
                <div class="session-chip-value">{{ $currentSession?->name ?? __('Not configured') }}</div>
            </div>
            <x-icon name="chevron-right" :size="14" style="margin-left:auto;color:#7c6f9e" />
        </div>
    </div>
</aside>
