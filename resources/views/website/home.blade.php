@extends('layouts.public')

@section('title', 'প্রচ্ছদ')

@php
    $messageCards = collect();

    if ($presidentMessage) {
        $messageCards->push([
            'model' => $presidentMessage,
            'title' => 'সভাপতির বাণী',
            'role' => 'সভাপতি',
            'fallback' => '',
            'route' => route('website.president-message'),
        ]);
    }

    foreach ($administrationMessages as $message) {
        $messageCards->push([
            'model' => $message,
            'title' => 'প্রশাসনের বাণী',
            'role' => 'প্রশাসন',
            'fallback' => '',
            'route' => $message->slug
                ? route('website.administration-message.show', $message->slug)
                : route('website.administration-message'),
        ]);
    }

    if ($principalMessage) {
        $messageCards->push([
            'model' => $principalMessage,
            'title' => 'প্রধান শিক্ষকের বাণী',
            'role' => 'প্রধান শিক্ষক',
            'fallback' => '',
            'route' => route('website.principal-message'),
        ]);
    }

    // Sample cards are only used when nothing has been published yet. As soon
    // as a real message exists the section follows the real count: one message
    // fills the row, two split into columns and three or more become a slider.
    if ($messageCards->isEmpty()) {
        $messageCards = collect([
            [
                'model' => null,
                'title' => 'সভাপতির বাণী',
                'role' => 'সভাপতি',
                'fallback' => 'শিক্ষা শুধু পরীক্ষায় ভালো ফল করার মাধ্যম নয়। এটি একজন মানুষকে দায়িত্বশীল, মানবিক ও আত্মনির্ভর করে গড়ে তোলে। আমাদের বিদ্যালয় সেই লক্ষ্যেই কাজ করে যাচ্ছে।',
                'route' => route('website.president-message'),
            ],
            [
                'model' => null,
                'title' => 'প্রশাসনের বাণী',
                'role' => 'প্রশাসন',
                'fallback' => 'একটি সুসংগঠিত শিক্ষা পরিবেশ, দক্ষ শিক্ষকমণ্ডলী ও অভিভাবকদের সহযোগিতা—এই তিনটি শক্তির সমন্বয়ে আমরা প্রতিটি শিক্ষার্থীর সম্ভাবনাকে বিকশিত করতে চাই।',
                'route' => route('website.administration-message'),
            ],
            [
                'model' => null,
                'title' => 'প্রধান শিক্ষকের বাণী',
                'role' => 'প্রধান শিক্ষক',
                'fallback' => 'প্রতিটি শিক্ষার্থীর ভেতরে আলাদা সম্ভাবনা রয়েছে। সঠিক দিকনির্দেশনা, অধ্যবসায় ও সুন্দর পরিবেশের মাধ্যমে সেই সম্ভাবনাকে বিকশিত করাই আমাদের প্রধান লক্ষ্য।',
                'route' => route('website.principal-message'),
            ],
        ]);
    }
@endphp

@section('content')
    @if (in_array('hero', $homeSections))
    <section class="hero"><div class="container hero-wrap">
        <div class="hero-slider">
            @if ($slider->isNotEmpty())
                @foreach ($slider as $slide)
                    @php
                        $hasText = trim((string) $slide->category) !== ''
                            || trim((string) $slide->title) !== ''
                            || trim((string) $slide->excerpt) !== ''
                            || trim(strip_tags((string) $slide->body)) !== ''
                            || filled($slide->button_label)
                            || filled($slide->link_url);
                    @endphp
                    <div class="hero-slide {{ $loop->first ? 'active' : '' }} {{ $hasText ? 'has-content' : '' }}"
                        @if ($slide->image_url) style="background-image:url('{{ $slide->image_url }}');background-size:cover;background-position:center" @else style="background-image:linear-gradient(135deg,#385d79,#8eabc0)" @endif>
                        @if ($hasText)
                            <div class="hero-content">
                                <span class="hero-kicker">{{ $slide->category ?: 'স্বাগতম' }}</span>
                                <h2>{{ $slide->title }}</h2>
                                <p>{{ $slide->excerpt ?: Str::limit(strip_tags($slide->body), 190) }}</p>
                                @if ($slide->link_url || $slide->button_label)
                                    <a class="btn btn-light" href="{{ $slide->link_url ?: route('website.about') }}">{{ $slide->button_label ?: 'বিস্তারিত দেখুন' }} →</a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
                @if ($slider->count() > 1)
                    <div class="hero-dots">
                        @foreach ($slider as $slide)
                            <span class="hero-dot {{ $loop->first ? 'active' : '' }}"></span>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="hero-slide active has-content" style="background-image:linear-gradient(135deg,#385d79,#8eabc0)">
                    <div class="hero-content">
                        <span class="hero-kicker">{{ $siteHeroTagline ?: 'স্বাগতম' }}</span>
                        <h2>{{ $siteHeroTitle ?: $siteNameBn }}</h2>
                        <p>{{ $siteHeroDescription ?: 'জ্ঞান, শৃঙ্খলা, নৈতিকতা ও আধুনিক প্রযুক্তির সমন্বয়ে শিক্ষার্থীদের ভবিষ্যতের জন্য প্রস্তুত করার প্রত্যয়।' }}</p>
                        @if ($siteHeroMenuName || $siteHeroMenuLink)
                            <a class="btn btn-light" href="{{ $siteHeroMenuLink ?: route('website.about') }}">{{ $siteHeroMenuName ?: 'বিস্তারিত দেখুন' }} →</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        <div class="quick-panel">
            @if ($featuredMenu && $featuredMenu->rootItems->isNotEmpty())
                @foreach ($featuredMenu->rootItems as $item)
                    <a class="quick-item" href="{{ $item->href }}" @if ($item->target === '_blank') target="_blank" rel="noopener" @endif>
                        <span class="quick-icon">@if ($item->icon)<x-icon :name="$item->icon" :size="24" />@else▣@endif</span>
                        <strong>{{ $item->title }}</strong>
                        @if ($item->subtitle)
                            <small>{{ $item->subtitle }}</small>
                        @endif
                    </a>
                @endforeach
            @else
                <a class="quick-item" href="{{ route('website.calendar') }}"><span class="quick-icon">▣</span><strong>ক্লাস রুটিন</strong><small>শ্রেণিভিত্তিক সময়সূচি</small></a>
                <a class="quick-item" href="{{ route('website.notices') }}"><span class="quick-icon">▤</span><strong>নোটিশ বোর্ড</strong><small>সর্বশেষ ঘোষণা</small></a>
                <a class="quick-item" href="{{ route('website.faculty') }}"><span class="quick-icon">♙</span><strong>শিক্ষকবৃন্দ</strong><small>অভিজ্ঞ শিক্ষক মণ্ডলী</small></a>
                <a class="quick-item" href="{{ route('website.downloads') }}"><span class="quick-icon">↓</span><strong>ডাউনলোড</strong><small>গুরুত্বপূর্ণ ফাইল</small></a>
                <a class="quick-item" href="{{ route('website.admission.apply') }}"><span class="quick-icon">✎</span><strong>অনলাইন আবেদন</strong><small>অনলাইন ভর্তি আবেদন</small></a>
                <a class="quick-item" href="{{ route('website.contact') }}"><span class="quick-icon">☎</span><strong>যোগাযোগ</strong><small>বিদ্যালয়ের সাথে যোগাযোগ</small></a>
            @endif
        </div>
    </div></section>
    @endif

    @if (in_array('introduction', $homeSections))
    <section class="section"><div class="container">
        <div class="section-head"><h2 class="section-title">{{ $introduction?->title ?: 'প্রতিষ্ঠানের পরিচিতি' }}</h2><a class="section-link" href="{{ route('website.about') }}">বিস্তারিত দেখুন →</a></div>
        <div class="feature-grid">
            <article class="card history-card reveal"><div class="history-inner">
                <div class="school-photo" @if ($introduction?->image_url) style="background-image:url('{{ $introduction->image_url }}');background-size:cover;background-position:center" @endif>
                    <span class="school-photo-label">{{ $introduction?->image_title ?: $siteNameBn }}</span>
                </div>
                <div class="history-text">
                    @if ($introduction)
                        <h3>{{ $introduction->subtitle ?: 'আলোকিত প্রজন্ম গড়ার অঙ্গীকার' }}</h3>
                        @if ($introduction->excerpt)
                            <div class="home-rich-body">{!! nl2br(e($introduction->excerpt)) !!}</div>
                        @endif
                        <a class="btn btn-purple" href="{{ $introduction->link_url ?: route('website.about') }}">{{ $introduction->button_label ?: 'আরও জানুন' }}</a>
                    @else
                        <h3>আলোকিত প্রজন্ম গড়ার অঙ্গীকার</h3>
                        <p>{{ $siteNameBn }} শিক্ষার্থীদের জ্ঞান অর্জনের পাশাপাশি নৈতিকতা, শৃঙ্খলা, নেতৃত্ব ও সৃজনশীলতার বিকাশে গুরুত্ব দেয়। আমাদের লক্ষ্য একটি নিরাপদ, আনন্দময় ও আধুনিক শিক্ষার পরিবেশ তৈরি করা।</p>
                        <p>দক্ষ শিক্ষক, নিয়মিত একাডেমিক কার্যক্রম এবং সহশিক্ষা কার্যক্রমের মাধ্যমে প্রতিটি শিক্ষার্থীকে তার সম্ভাবনার সর্বোচ্চ পর্যায়ে পৌঁছাতে সহায়তা করা হয়।</p>
                        <a class="btn btn-purple" href="{{ route('website.about') }}">আরও জানুন</a>
                    @endif
                </div>
            </div></article>
            <aside class="card notice-card reveal"><div class="panel-head"><h3>নোটিশ বোর্ড</h3><a href="{{ route('website.notices') }}">সব দেখুন</a></div>
                @forelse ($notices as $notice)
                    <a class="notice-row" href="{{ route('website.notice', $notice) }}">
                        <span class="notice-date">{{ $notice->publish_at?->format('d') ?? $notice->created_at?->format('d') }}<br>{{ $notice->publish_at?->format('M') ?? $notice->created_at?->format('M') }}</span>
                        <span>{{ $notice->title }}</span>
                    </a>
                @empty
                    <div class="notice-row"><span class="notice-date">—</span><span>বর্তমানে কোনো প্রকাশিত নোটিশ নেই।</span></div>
                @endforelse
            </aside>
        </div>
    </div></section>
    @endif

    @if (in_array('messages', $homeSections))
    <section class="section" style="background:#f7f8fb"><div class="container">
        <div class="section-head">
            <h2 class="section-title">প্রশাসনের বাণী</h2>
            @if (count($messageCards) > 1)
                <div class="message-nav" aria-label="প্রশাসনের বাণী নেভিগেশন">
                    <button type="button" class="message-arrow message-prev" aria-label="আগের বাণী">←</button>
                    <button type="button" class="message-arrow message-next" aria-label="পরের বাণী">→</button>
                </div>
            @endif
        </div>
        <div class="message-slider" style="--message-visible: {{ min(count($messageCards), 2) }}" data-visible-desktop="2" data-visible-tablet="2" data-visible-mobile="1">
            <div class="message-track">
                @foreach ($messageCards as $card)
                    @php($message = $card['model'])
                    <article class="card message-card reveal">
                        <div class="person-photo" @if ($message?->image_url) style="background-image:url('{{ $message->image_url }}');background-size:cover;background-position:center;color:transparent" @endif>{{ $message ? '' : $card['role'] }}</div>
                        <div>
                            <h3>{{ $message?->title ?: $card['title'] }}</h3>
                            <p class="message-excerpt">{{ $message?->excerpt ?: Str::limit(strip_tags($message?->body ?? ''), 220) ?: $card['fallback'] }}</p>
                            <p style="margin-top:8px"><b>{{ $message?->category ?: $card['role'] }}</b><br>{{ $siteNameBn }}</p>
                            <a class="section-link" href="{{ $card['route'] }}">বিস্তারিত পড়ুন →</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </div></section>
    @endif

    @if (in_array('academic', $homeSections))
    <section class="section"><div class="container">
        <div class="section-head"><h2 class="section-title">একাডেমিক কর্নার</h2><a class="section-link" href="{{ route('website.academics') }}">সব দেখুন →</a></div>
        <div class="academic-grid">
            @if ($academicItems->isNotEmpty())
                @foreach ($academicItems as $item)
                    @php($academicIcon = \App\Support\WebsiteIcons::exists($item->category) ? $item->category : null)
                    <a class="card academic-card reveal" href="{{ $item->link_url ?: route('website.academics') }}">
                        @if ($item->image_url)
                            <img src="{{ $item->image_url }}" alt="{{ $item->title }}" style="width:48px;height:48px;object-fit:cover;border-radius:12px;margin-bottom:8px">
                        @elseif ($academicIcon)
                            <div class="academic-icon"><x-icon :name="$academicIcon" :size="30" /></div>
                        @else
                            <div class="academic-icon">{{ $item->category ?: '◆' }}</div>
                        @endif
                        <h3>{{ $item->title }}</h3>
                        <p>{{ $item->subtitle ?: ($item->excerpt ?: Str::limit(strip_tags($item->body), 100)) }}</p>
                    </a>
                @endforeach
            @else
                <a class="card academic-card reveal" href="{{ route('website.academics') }}"><div class="academic-icon"><x-icon name="graduation-cap" :size="30" /></div><h3>ক্লাস ও বিষয়</h3><p>{{ $stats['classes'] }}টি ক্লাসের বিষয়ভিত্তিক তথ্য</p></a>
                <a class="card academic-card reveal" href="{{ route('website.faculty') }}"><div class="academic-icon"><x-icon name="users" :size="30" /></div><h3>শিক্ষকবৃন্দ</h3><p>অভিজ্ঞ ও নিবেদিত শিক্ষক মণ্ডলী</p></a>
                <a class="card academic-card reveal" href="{{ route('website.calendar') }}"><div class="academic-icon"><x-icon name="calendar" :size="30" /></div><h3>ক্যালেন্ডার</h3><p>পরীক্ষা ও একাডেমিক সময়সূচি</p></a>
                <a class="card academic-card reveal" href="{{ route('website.downloads') }}"><div class="academic-icon"><x-icon name="download" :size="30" /></div><h3>ডাউনলোড</h3><p>সিলেবাস ও গুরুত্বপূর্ণ ফাইল</p></a>
            @endif
        </div>
    </div></section>
    @endif

    @if (in_array('news', $homeSections))
    <section class="section"><div class="container">
        <div class="section-head"><h2 class="section-title">সর্বশেষ খবর ও অনুষ্ঠান</h2><a class="section-link" href="{{ route('website.news') }}">সব খবর →</a></div>
        <div class="news-grid">
            <div class="card news-list reveal">
                @forelse ($news as $item)
                    <a class="news-item" href="{{ route('website.news.item', $item->slug) }}">
                        <div class="news-thumb" @if ($item->image_url) style="background-image:url('{{ $item->image_url }}');background-size:cover;background-position:center" @endif></div>
                        <div><h3>{{ $item->title }}</h3><p>{{ $item->excerpt ?: Str::limit(strip_tags($item->body), 110) }}</p></div>
                    </a>
                @empty
                    <div class="news-item"><div class="news-thumb"></div><div><h3>খবর শীঘ্রই প্রকাশিত হবে</h3><p>প্রকাশিত সংবাদ এখানে দেখা যাবে।</p></div></div>
                @endforelse
            </div>
            <div class="card panel-card reveal"><div class="panel-head"><h3>ফটো গ্যালারি</h3><a href="{{ route('website.gallery') }}">সব ছবি</a></div><div class="gallery-grid">
                @forelse ($gallery as $item)
                    @if ($item->image_url)
                        <button type="button" class="gallery-item" data-home-gallery-image="{{ $item->image_url }}" data-home-gallery-title="{{ $item->title }}" aria-label="{{ $item->title }} ছবিটি দেখুন">
                            <img src="{{ $item->image_url }}" alt="{{ $item->title }}" style="width:100%;height:100%;object-fit:cover">
                            <span>{{ $item->title }}</span>
                        </button>
                    @else
                        <a class="gallery-item" href="{{ route('website.gallery') }}"><span>{{ $item->title }}</span></a>
                    @endif
                @empty
                    <a class="gallery-item" href="{{ route('website.gallery') }}"><span>বিদ্যালয় ক্যাম্পাস</span></a>
                    <a class="gallery-item" href="{{ route('website.gallery') }}"><span>শ্রেণিকক্ষ</span></a>
                @endforelse
            </div></div>
        </div>
        <div class="card panel-card reveal" style="margin-top:16px">
            <div class="panel-head"><h3>আসন্ন অনুষ্ঠান</h3><a href="{{ route('website.events') }}">সব অনুষ্ঠান</a></div>
            <div class="event-list">
                @forelse ($events as $event)
                    <a class="event-row" href="{{ route('website.events') }}">
                        <span class="event-date"><strong>{{ $event->published_at?->format('d') ?? '—' }}</strong><small>{{ $event->published_at?->format('M') ?? '' }}</small></span>
                        <span><strong>{{ $event->title }}</strong><small>{{ $event->excerpt ?: Str::limit(strip_tags($event->body), 140) }}</small></span>
                    </a>
                @empty
                    <div class="event-row"><span class="event-date"><strong>—</strong><small></small></span><span><strong>আসন্ন কোনো অনুষ্ঠান নেই</strong><small>নতুন অনুষ্ঠান প্রকাশিত হলে এখানে দেখা যাবে।</small></span></div>
                @endforelse
            </div>
        </div>
    </div></section>
    @endif

    @if (in_array('stats', $homeSections))
    <section class="section"><div class="container">
        <div class="section-head"><h2 class="section-title">এক নজরে</h2></div>
        <div class="card stats"><div class="stats-grid">
            <div class="stat"><strong>{{ $stats['classes'] }}</strong><span>ক্লাস</span></div>
            <div class="stat"><strong>{{ $stats['notices'] }}</strong><span>প্রকাশিত নোটিশ</span></div>
            <div class="stat"><strong>{{ $stats['news'] }}</strong><span>সংবাদ</span></div>
            <div class="stat"><strong>{{ $stats['events'] }}</strong><span>ইভেন্ট</span></div>
        </div></div>
    </div></section>
    @endif

    @if (in_array('contact', $homeSections))
    <section class="section"><div class="container"><div class="contact-grid">
        <div class="card contact-card reveal"><div class="section-head"><h2 class="section-title">যোগাযোগ</h2></div>
            @if ($siteAddress)<div class="contact-item"><span>⌖</span><div><b>ঠিকানা</b><span>{{ $siteAddress }}</span></div></div>@endif
            @if ($sitePhone)<div class="contact-item"><span>☎</span><div><b>ফোন</b><span>{{ $sitePhone }}</span></div></div>@endif
            @if ($siteEmail)<div class="contact-item"><span>✉</span><div><b>ইমেইল</b><span>{{ $siteEmail }}</span></div></div>@endif
            <p style="margin-top:12px"><a class="btn btn-purple" href="{{ route('website.contact') }}">যোগাযোগ করুন</a></p>
        </div>
        @if ($siteMapSrc)
            <div class="map-box card reveal" style="padding:0;overflow:hidden;min-height:300px">
                <iframe src="{{ $siteMapSrc }}" title="{{ $siteNameBn }} map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen style="border:0;display:block;width:100%;min-height:300px"></iframe>
            </div>
        @else
            <div class="map-box card reveal" style="display:flex;align-items:center;justify-content:center;min-height:220px;padding:24px;text-align:center">
                <div><strong>{{ $siteNameBn }}</strong><p style="margin-top:8px;color:#6e7988">{{ $siteAddress }}</p><p style="color:#6e7988">{{ $sitePhone }}{{ $sitePhone && $siteEmail ? ' · ' : '' }}{{ $siteEmail }}</p></div>
            </div>
        @endif
    </div></div></section>
    @endif

    <dialog class="gallery-lightbox" id="home-gallery-lightbox" aria-label="Gallery image preview">
        <div class="gallery-shell">
            <img id="home-gallery-image" src="" alt="">
            <div class="gallery-bar">
                <span id="home-gallery-caption"></span>
                <div class="actions">
                    <button type="button" data-home-gallery-prev aria-label="Previous image">←</button>
                    <button type="button" data-home-gallery-next aria-label="Next image">→</button>
                    <button type="button" data-home-gallery-close>বন্ধ করুন</button>
                </div>
            </div>
        </div>
    </dialog>
@endsection

@push('head')
    <style>
        .home-rich-body { color: #4b5565; }
        .home-rich-body p { margin: 0 0 10px; }
        .home-rich-body p:last-child { margin-bottom: 14px; }
        .home-rich-body img { max-width: 100%; height: auto; border-radius: 10px; }
        .person-photo { background-size: cover; background-position: center; }
        dialog.gallery-lightbox { border: 0; padding: 0; background: transparent; max-width: min(1100px, 95vw); max-height: 94vh; }
        dialog.gallery-lightbox::backdrop { background: rgba(5, 12, 24, .86); }
        .gallery-shell { background: #0c1424; border-radius: 14px; overflow: hidden; box-shadow: 0 30px 90px rgba(0,0,0,.5); }
        .gallery-shell img { display: block; max-width: 100%; max-height: 80vh; margin: 0 auto; }
        .gallery-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; color: #e6edf7; font-size: 13px; }
        .gallery-bar .actions { display: flex; gap: 8px; }
        .gallery-bar button { border: 1px solid rgba(255,255,255,.25); background: rgba(255,255,255,.1); color: #fff; border-radius: 8px; padding: 6px 10px; cursor: pointer; }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const items = Array.from(document.querySelectorAll('[data-home-gallery-image]'));
            const dialog = document.getElementById('home-gallery-lightbox');
            const image = document.getElementById('home-gallery-image');
            const caption = document.getElementById('home-gallery-caption');
            if (!items.length || !dialog || !image || !caption) return;

            let active = 0;
            const show = (index) => {
                active = (index + items.length) % items.length;
                const item = items[active];
                image.src = item.dataset.homeGalleryImage;
                image.alt = item.dataset.homeGalleryTitle || '';
                caption.textContent = item.dataset.homeGalleryTitle || '';
                if (!dialog.open) dialog.showModal();
            };

            items.forEach((item, index) => item.addEventListener('click', () => show(index)));
            document.querySelector('[data-home-gallery-prev]')?.addEventListener('click', () => show(active - 1));
            document.querySelector('[data-home-gallery-next]')?.addEventListener('click', () => show(active + 1));
            document.querySelector('[data-home-gallery-close]')?.addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
            dialog.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft') show(active - 1);
                if (event.key === 'ArrowRight') show(active + 1);
            });
        })();
    </script>
@endpush
