@php
    /**
     * Public inner-page sidebar.
     *
     * Mirrors the school-web `.side-menu` card: navy heading plus a stack of
     * quick links. Links come from the Website Management "sidebar" menu when it
     * exists, otherwise a sensible default set is shown so the design never
     * breaks on a fresh install.
     */
    $sidebarMenu = $sidebarMenu ?? null;
    $sidebarTitle = $sidebarTitle ?? ($sidebarMenu?->name ?: 'একাডেমিক তথ্য');
    $currentUrl = rtrim(url()->current(), '/');

    $defaultLinks = [
        ['title' => 'বিদ্যালয় পরিচিতি', 'href' => route('website.about')],
        ['title' => 'একাডেমিক কার্যক্রম', 'href' => route('website.academics')],
        ['title' => 'শিক্ষকবৃন্দ', 'href' => route('website.faculty')],
        ['title' => 'ভর্তি তথ্য', 'href' => route('website.admission')],
        ['title' => 'নোটিশ বোর্ড', 'href' => route('website.notices')],
        ['title' => 'একাডেমিক ক্যালেন্ডার', 'href' => route('website.calendar')],
        ['title' => 'ক্লাস রুটিন', 'href' => route('website.class-routine')],
        ['title' => 'ফলাফল', 'href' => route('website.results')],
        ['title' => 'ডাউনলোড', 'href' => route('website.downloads')],
        ['title' => 'যোগাযোগ', 'href' => route('website.contact')],
    ];

    $menuItems = ($sidebarMenu && $sidebarMenu->rootItems->isNotEmpty())
        ? $sidebarMenu->rootItems
        : null;

    $isActive = static function (?string $href) use ($currentUrl): bool {
        if (! $href || $href === '#' || preg_match('#^(https?:)?//#i', $href)) {
            return false;
        }

        return rtrim(url($href), '/') === $currentUrl;
    };
@endphp

<aside class="card side-menu" aria-label="{{ $sidebarTitle }}">
    <h3>{{ $sidebarTitle }}</h3>

    @if ($menuItems)
        @foreach ($menuItems as $item)
            <a href="{{ $item->href }}"
               class="{{ $isActive($item->href) ? 'active' : '' }}"
               @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>{{ $item->title }}</a>
            @foreach ($item->children as $child)
                <a href="{{ $child->href }}"
                   class="{{ $isActive($child->href) ? 'active' : '' }}"
                   style="padding-left:28px"
                   @if ($child->target === '_blank') target="_blank" rel="noopener" @endif>{{ $child->title }}</a>
            @endforeach
        @endforeach
    @else
        @foreach ($defaultLinks as $link)
            <a href="{{ $link['href'] }}" class="{{ $isActive($link['href']) ? 'active' : '' }}">{{ $link['title'] }}</a>
        @endforeach
    @endif
</aside>
