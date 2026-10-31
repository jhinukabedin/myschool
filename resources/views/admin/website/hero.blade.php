@extends('layouts.app')

@section('title', 'Hero Section')

@php
    $v = fn (string $key, mixed $default = null) => old($key, $settings[$key] ?? $default);
    $sliderCount = \App\Models\WebsiteContent::query()->ofType('slider')->count();
@endphp

@section('content')
    <x-page-header eyebrow="Website content" title="Hero Section"
        subtitle="Manage the homepage hero: the slider carousel and the featured menu buttons.">
        <x-slot:actions>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="arrow-left">Website dashboard</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    {{-- Sub-section navigation --}}
    <div class="grid-2" style="margin-bottom:16px">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Slider Area</h3>
                    <p class="card-sub">{{ $sliderCount }} slider record(s). Add, edit, delete and enable/disable homepage slides.</p>
                </div>
            </div>
            <div class="card-body">
                <x-button :href="route('website-admin.sliders')" variant="primary" icon="images">Manage Slider Area</x-button>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Featured Menu</h3>
                    <p class="card-sub">Buttons shown in the hero featured area.</p>
                </div>
            </div>
            <div class="card-body">
                <x-button :href="route('website-admin.featured-menu')" variant="primary" icon="menu">Manage Featured Menu</x-button>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('website-admin.hero.update') }}">
        @csrf
        @method('PUT')

        <div class="grid-main-side">
            <div class="stack">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Hero Default Content</h3>
                            <p class="card-sub">Used when no slider is enabled, and as the hero heading.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2">
                                <x-input name="website_hero_tagline" label="Tagline" :value="$v('website_hero_tagline')" placeholder="শিক্ষা • শৃঙ্খলা • নৈতিকতা" />
                            </div>
                            <div class="span-2">
                                <x-input name="website_hero_title" label="Slider Title" :value="$v('website_hero_title')" placeholder="Welcome to our school" />
                            </div>
                            <div class="span-2">
                                <x-textarea name="website_hero_description" label="Slider Description" rows="3" :value="$v('website_hero_description')" placeholder="Short description shown under the title." />
                            </div>
                            <x-input name="website_hero_menu_name" label="Menu / Button Name" :value="$v('website_hero_menu_name')" placeholder="Admission" />
                            <x-input name="website_hero_menu_link" label="Menu / Button Link" :value="$v('website_hero_menu_link')" placeholder="/admission/apply" help="Relative paths and full URLs are allowed." />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <x-button type="submit" variant="primary" icon="save" class="btn-block">Save Hero Section Settings</x-button>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Preview</h3><p class="card-sub">Hero section on the homepage.</p></div></div>
                    <div class="card-body">
                        <div style="border:1px solid var(--border);border-radius:10px;padding:24px;background:linear-gradient(135deg,#0f2f50,#385d79);color:#fff">
                            <div style="display:inline-block;background:rgba(255,255,255,.15);border-radius:999px;padding:5px 12px;font-size:11px;margin-bottom:12px">
                                {{ $v('website_hero_tagline') ?: 'Tagline' }}
                            </div>
                            <h2 style="font-size:22px;margin:0 0 10px;line-height:1.25">{{ $v('website_hero_title') ?: 'Hero title' }}</h2>
                            <p style="font-size:12.5px;color:#d8e3ef;line-height:1.6;margin:0 0 16px">{{ $v('website_hero_description') ?: 'Hero description' }}</p>
                            <span style="display:inline-block;background:#d9ad55;color:#102f50;padding:9px 18px;border-radius:8px;font-weight:700;font-size:12px">
                                {{ $v('website_hero_menu_name') ?: 'Button' }} →
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection