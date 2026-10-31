@extends('layouts.app')

@section('title', 'Website Design')

@php
    $v = fn (string $key, mixed $default = null) => old($key, $settings[$key] ?? $default);
    $headerLabels = [
        'classic' => ['Classic blue', 'Logo left, wide navigation, utility bar'],
        'centered' => ['Centered crest', 'Centered identity and navigation'],
        'split' => ['Split banner', 'Large identity block with admission CTA'],
        'minimal' => ['Minimal compact', 'Compact header without the utility bar'],
    ];
    $footerLabels = [
        'classic' => ['Classic four columns', 'About, menu, contact and legal bar'],
        'columns' => ['Wide columns', 'Spacious editorial footer'],
        'compact' => ['Compact bar', 'Compact brand and links'],
        'accent' => ['Accent panel', 'Highlighted footer with contact panel'],
    ];
@endphp

@section('content')
    <x-page-header eyebrow="Website content" title="Design, Logo & Header/Footer"
        subtitle="Change the public identity without editing code. Pick a header, footer and colour theme, then update the text and logo.">
        <x-slot:actions>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="arrow-left">Website dashboard</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    <form method="POST" action="{{ route('website-admin.design.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid-main-side">
            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Header style</h3><p class="card-sub">Four responsive layouts. The menu itself is managed separately.</p></div></div>
                    <div class="card-body">
                        <div class="grid-2">
                            @foreach ($headerStyles as $value => $label)
                                <label class="card" style="cursor:pointer;box-shadow:none;border:2px solid {{ $v('website_header_style') === $value ? 'var(--primary, #385d79)' : 'var(--border)' }};padding:12px">
                                    <div style="display:flex;gap:10px;align-items:flex-start">
                                        <input type="radio" name="website_header_style" value="{{ $value }}" @checked($v('website_header_style') === $value)>
                                        <div>
                                            <strong>{{ $headerLabels[$value][0] ?? $label }}</strong>
                                            <p class="cell-sub" style="margin:3px 0 8px">{{ $headerLabels[$value][1] ?? '' }}</p>
                                            <div class="design-preview header-preview preview-{{ $value }}">
                                                <span></span><i></i><b></b>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('website_header_style')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Footer style</h3><p class="card-sub">Four responsive footer layouts using the same menu and contact details.</p></div></div>
                    <div class="card-body">
                        <div class="grid-2">
                            @foreach ($footerStyles as $value => $label)
                                <label class="card" style="cursor:pointer;box-shadow:none;border:2px solid {{ $v('website_footer_style') === $value ? 'var(--primary, #385d79)' : 'var(--border)' }};padding:12px">
                                    <div style="display:flex;gap:10px;align-items:flex-start">
                                        <input type="radio" name="website_footer_style" value="{{ $value }}" @checked($v('website_footer_style') === $value)>
                                        <div>
                                            <strong>{{ $footerLabels[$value][0] ?? $label }}</strong>
                                            <p class="cell-sub" style="margin:3px 0 8px">{{ $footerLabels[$value][1] ?? '' }}</p>
                                            <div class="design-preview footer-preview preview-{{ $value }}"><span></span><i></i><b></b></div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        @error('website_footer_style')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Identity & header text</h3><p class="card-sub">Used in the header, footer, login pages and browser title</p></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="website_name_bn" label="School name (Bangla)" :value="$v('website_name_bn')" required />
                            <x-input name="website_name_en" label="School name (English)" :value="$v('website_name_en')" required />
                            <div class="span-2"><x-input name="website_tagline" label="Tagline" :value="$v('website_tagline')" help="Shown in the footer brand block and metadata." /></div>
                            <x-input name="website_logo_mark" label="Short logo mark" :value="$v('website_logo_mark')" maxlength="4" help="Used when no logo image is uploaded, e.g. HS." />
                            <x-input name="website_header_cta_label" label="Header button label" :value="$v('website_header_cta_label')" placeholder="ভর্তি আবেদন" />
                            <x-input name="website_header_cta_url" label="Header button link" :value="$v('website_header_cta_url')" placeholder="/admission/apply" help="Relative paths such as /admission/apply are allowed." />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Utility bar</h3><p class="card-sub">Thin bar above the main header, showing the school message and today's date</p></div></div>
                    <div class="form-section">
                        <div class="stack">
                            <x-input name="website_topbar_text" label="Utility bar text" :value="$v('website_topbar_text')" help="Leave blank to use the tagline. The date appears after this text." />
                            <div>
                                <input type="hidden" name="website_show_topbar" value="0">
                                <label class="checkbox">
                                    <input type="checkbox" name="website_show_topbar" value="1" @checked($v('website_show_topbar') === '1')>
                                    <span>Show the utility bar above the header (text and date)</span>
                                </label>
                            </div>
                            <div class="info-box">The date follows School Settings → Date &amp; Calendar Settings. The bar is hidden on small screens and when the Minimal header style is selected.</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Footer text</h3><p class="card-sub">Editable description, copyright and office note</p></div></div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2"><x-textarea name="website_footer_about" label="Footer introduction" rows="3" :value="$v('website_footer_about')" maxlength="500" /></div>
                            <div class="span-2"><x-input name="website_footer_copyright" label="Copyright line" :value="$v('website_footer_copyright')" help="Use :year for the current year." /></div>
                            <div class="span-2"><x-input name="website_footer_note" label="Footer note" :value="$v('website_footer_note')" /></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Colour theme</h3><p class="card-sub">Accent colours across the public website</p></div></div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px">
                            @foreach ($themes as $value => $label)
                                <label style="cursor:pointer;border:2px solid {{ $v('website_theme') === $value ? 'var(--primary, #385d79)' : 'var(--border)' }};border-radius:10px;padding:10px;text-align:center">
                                    <input type="radio" name="website_theme" value="{{ $value }}" @checked($v('website_theme') === $value)>
                                    <span class="theme-swatch theme-{{ $value }}" style="display:block;height:34px;border-radius:7px;margin:8px 0"></span>
                                    <strong style="font-size:12px">{{ $label }}</strong>
                                </label>
                            @endforeach
                        </div>
                        @error('website_theme')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">School logo</h3><p class="card-sub">PNG, JPG or WebP. A wide or square logo both work.</p></div></div>
                    <div class="card-body">
                        <div style="border:1px dashed var(--border);border-radius:12px;padding:18px;text-align:center;background:#f8fafc;margin-bottom:14px">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Current website logo" style="max-height:100px;max-width:100%;margin:auto">
                            @else
                                <div class="brand-mark" style="margin:auto;width:64px;height:64px;font-size:22px">{{ $v('website_logo_mark') ?: 'HS' }}</div>
                                <p class="cell-sub" style="margin-top:8px">No logo image uploaded. The logo mark is used.</p>
                            @endif
                        </div>
                        <x-input name="logo" label="Upload new logo" type="file" accept="image/jpeg,image/png,image/webp" help="Maximum 4 MB. The old file is deleted when it is no longer used." />
                        @if ($logoUrl)
                            <label class="checkbox" style="margin-top:10px"><input type="checkbox" name="remove_logo" value="1"> <span>Remove the uploaded logo and use the logo mark</span></label>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Social links</h3><p class="card-sub">Appear in the header and footer</p></div></div>
                    <div class="form-section">
                        <div class="stack">
                            <x-input name="website_facebook" label="Facebook URL" type="url" :value="$v('website_facebook')" />
                            <x-input name="website_x" label="X / Twitter URL" type="url" :value="$v('website_x')" />
                            <x-input name="website_youtube" label="YouTube URL" type="url" :value="$v('website_youtube')" />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <p class="muted" style="font-size:12px;margin:0 0 12px">Changes are applied to the public site immediately after saving. Menus are controlled from the menu builder.</p>
                        <x-button type="submit" variant="primary" icon="save" class="btn-block">Save website design</x-button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('head')
    <style>
        .design-preview { height:52px;border-radius:7px;background:#eef2f7;border:1px solid #dbe2ec;position:relative;overflow:hidden;padding:7px;display:flex;gap:5px;align-items:flex-end }
        .design-preview span { display:block;width:24px;height:14px;border-radius:3px;background:#385d79 }
        .design-preview i { display:block;flex:1;height:8px;border-radius:3px;background:#9fb2c8 }
        .design-preview b { display:block;width:18px;height:8px;border-radius:3px;background:#d9ad55 }
        .header-preview.preview-centered { justify-content:center;align-items:center }
        .header-preview.preview-centered span { height:20px;width:20px }
        .header-preview.preview-split { background:#0f2f50 }
        .header-preview.preview-split span { background:#d9ad55;height:24px;width:30px }
        .header-preview.preview-split i { background:#c7d7e8 }
        .header-preview.preview-minimal span { width:16px;height:10px }
        .header-preview.preview-minimal b { display:none }
        .footer-preview { height:56px;background:#102f50;align-items:flex-start }
        .footer-preview span { background:#d9ad55;height:10px;width:28px }
        .footer-preview i { background:#8ea9c6;height:6px }
        .footer-preview.preview-columns { background:#163b61;gap:10px }
        .footer-preview.preview-compact { height:34px;background:#0b243d;align-items:center }
        .footer-preview.preview-accent { background:linear-gradient(90deg,#673095,#3b1d5e) }
        .footer-preview.preview-accent b { background:#7ee0a2 }
        .theme-navy { background:linear-gradient(90deg,#102f50,#d9ad55) }
        .theme-emerald { background:linear-gradient(90deg,#145c4b,#d6b36a) }
        .theme-maroon { background:linear-gradient(90deg,#6b1f2a,#e7c89a) }
        .theme-royal { background:linear-gradient(90deg,#3a1b66,#c9a7ff) }
    </style>
@endpush
