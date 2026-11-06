@php
    $user = auth()->user();
    $hour = (int) now()->format('H');
    $greetingKey = $hour < 12 ? 'Good morning, :name' : ($hour < 17 ? 'Good afternoon, :name' : 'Good evening, :name');
    $greeting = __($greetingKey, ['name' => Str::before($user?->name ?? __('User'), ' ')]);
    $locales = config('app.available_locales', ['en' => 'English']);
@endphp

<header class="topbar">
    <button type="button" class="sidebar-toggle" data-sidebar-toggle onclick="window.toggleSidebar && window.toggleSidebar()" aria-controls="app-sidebar" aria-expanded="true" aria-label="{{ __('Hide sidebar') }}" title="{{ __('Hide sidebar') }}">
        <x-icon name="menu" :size="19" />
    </button>

    <button type="button" class="menu-btn" @click="sidebarOpen = true" aria-label="{{ __('Open navigation') }}">
        <x-icon name="menu" :size="19" />
    </button>

    <div class="topbar-left">
        <div class="eyebrow">
            <x-icon name="activity" :size="11" />
            <span>{{ $greeting }}</span>
        </div>
        <div class="topbar-heading">
            <div class="topbar-title">@yield('title', __('Dashboard'))</div>
            <x-date-display class="topbar-date" :icon="true" />
        </div>
    </div>

    <div class="topbar-right">
        <form method="GET" action="{{ route('students.index') }}" class="top-search" role="search">
            <x-icon name="search" :size="15" />
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search students...') }}" aria-label="{{ __('Search students') }}">
            <button type="button" class="search-kbd" data-command-open title="{{ __('Open command palette (Ctrl+K)') }}" aria-label="{{ __('Open command palette') }}">
                <span>Ctrl</span><span>K</span>
            </button>
        </form>

        @if ($academicSessions->isNotEmpty())
            <label class="session-selector" title="{{ __('Academic session') }}">
                <x-icon name="calendar" :size="14" />
                <select aria-label="{{ __('Switch academic session') }}" onchange="const u = new URL(window.location.href); u.searchParams.set('session', this.value); if (window.Turbo) { window.Turbo.visit(u.toString()); } else { window.location.href = u.toString(); }">
                    @foreach ($academicSessions as $sessionOption)
                        <option value="{{ $sessionOption->id }}" @selected($currentSession?->id === $sessionOption->id)>
                            {{ $sessionOption->name }}{{ $sessionOption->is_current ? ' · '.__('Current') : '' }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif

        <div class="lang-switch" role="group" aria-label="{{ __('Language') }}">
            @foreach ($locales as $code => $label)
                <a
                    href="{{ route('language.switch', $code) }}"
                    class="lang-option {{ app()->getLocale() === $code ? 'active' : '' }}"
                    hreflang="{{ $code }}"
                    @if (app()->getLocale() === $code) aria-current="true" @endif
                    title="{{ $label }}"
                >{{ $code === 'bn' ? 'বাংলা' : strtoupper($code) }}</a>
            @endforeach
        </div>

        <button type="button" class="icon-btn theme-toggle" onclick="window.toggleTheme && window.toggleTheme()" aria-label="{{ __('Toggle theme') }}" title="{{ __('Toggle theme') }}">
            <x-icon name="sun" :size="17" class="theme-icon theme-icon-sun" />
            <x-icon name="moon" :size="17" class="theme-icon theme-icon-moon" />
        </button>

        <div x-data="{ open: false }" style="position:relative">
            <button type="button" class="icon-btn" @click="open = !open" @click.outside="open = false" aria-label="{{ __('Notifications') }}">
                <x-icon name="bell" :size="17" />
                @if ($topNotices->isNotEmpty())
                    <span class="notif-dot"></span>
                @endif
            </button>

            <div class="dropdown wide" x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false">
                <div class="dropdown-header">{{ __('Latest notices') }}</div>
                @forelse ($topNotices as $notice)
                    <a href="{{ route('notices.index') }}" class="dropdown-notice">
                        <div class="t">{{ $notice->title }}</div>
                        <div class="d">{{ $notice->publish_at?->diffForHumans() ?? __('Draft') }}</div>
                    </a>
                @empty
                    <div class="dropdown-notice muted-2" style="font-size:12px">{{ __('No notices published yet.') }}</div>
                @endforelse
                <div class="dropdown-divider"></div>
                <a href="{{ route('notices.index') }}" class="dropdown-item">{{ __('View all notices') }} <x-icon name="arrow-right" :size="13" style="margin-left:auto" /></a>
            </div>
        </div>

        <div x-data="{ open: false }" style="position:relative">
            <button type="button" class="profile-btn" @click="open = !open" @click.outside="open = false" aria-label="{{ __('Account menu') }}">
                <span class="avatar">
                    @if ($user?->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="">
                    @else
                        {{ $user?->initials ?? 'U' }}
                    @endif
                </span>
                <span class="profile-meta">
                    <span class="name">{{ $user?->name }}</span>
                    <span class="role">{{ $user?->role?->name ?? __('User') }}</span>
                </span>
                <x-icon name="chevron-down" :size="14" style="color:var(--muted-2)" />
            </button>

            <div class="dropdown" x-show="open" x-cloak x-transition.origin.top.right @click.outside="open = false">
                <div style="padding:10px">
                    <div style="font-size:13px;font-weight:600">{{ $user?->name }}</div>
                    <div style="font-size:11px;color:var(--muted-2);margin-top:2px">{{ $user?->email }}</div>
                </div>
                <div class="dropdown-divider"></div>
                @can('settings.view')
                    <a href="{{ route('settings.index') }}" class="dropdown-item"><x-icon name="settings" :size="15" /> {{ __('Settings') }}</a>
                @endcan
                <a href="{{ route('password.change') }}" class="dropdown-item"><x-icon name="key" :size="15" /> {{ __('Change password') }}</a>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                    @csrf
                    <button type="submit" class="dropdown-item" style="width:100%;border:0;background:transparent">
                        <x-icon name="log-out" :size="15" /> {{ __('Sign out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
