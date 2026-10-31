@extends('layouts.app')

@section('title', 'Footer Area')

@php
    $v = fn (string $key, mixed $default = null) => old($key, $settings[$key] ?? $default);
@endphp

@section('content')
    <x-page-header eyebrow="Website content" title="Footer Area"
        subtitle="Manage the four default footer columns: logo & about, institution menu, important links and contact details.">
        <x-slot:actions>
            <x-button :href="route('website-admin.index')" variant="secondary" icon="arrow-left">Website dashboard</x-button>
            <x-button :href="route('website.home')" variant="secondary" icon="external-link" target="_blank">View website</x-button>
        </x-slot:actions>
    </x-page-header>


    <form method="POST" action="{{ route('website-admin.footer.update') }}">
        @csrf
        @method('PUT')

        <div class="grid-main-side">
            <div class="stack">
                {{-- Column 1 --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Column 1 — Logo &amp; About</h3>
                            <p class="card-sub">The logo is reused from Design → School logo.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2">
                                <div style="border:1px dashed var(--border);border-radius:10px;padding:16px;text-align:center;background:#f8fafc;margin-bottom:10px">
                                    @if ($logoUrl)
                                        <img src="{{ $logoUrl }}" alt="Website logo" style="max-height:70px;max-width:100%;margin:auto">
                                    @else
                                        <div class="brand-mark" style="margin:auto">{{ $logoMark }}</div>
                                        <p class="cell-sub" style="margin-top:6px">No logo uploaded yet</p>
                                    @endif
                                </div>
                                <p class="field-hint">Change the logo in <a href="{{ route('website-admin.design') }}">Design &amp; logo</a>.</p>
                            </div>
                            <div class="span-2">
                                <x-textarea name="website_footer_about" label="About Info" rows="4" :value="$v('website_footer_about')" maxlength="800" help="Short introduction shown under the logo in the footer." />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Column headings --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Column 2 &amp; 3 headings</h3>
                            <p class="card-sub">The heading text shown above each footer menu.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="website_footer_institution_title" label="প্রতিষ্ঠান heading" :value="$v('website_footer_institution_title', 'প্রতিষ্ঠান')" />
                            <x-input name="website_footer_links_title" label="গুরুত্বপূর্ণ লিংক heading" :value="$v('website_footer_links_title', 'গুরুত্বপূর্ণ লিংক')" />
                        </div>
                    </div>
                </div>

                {{-- Column 4 --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Column 4 — যোগাযোগ</h3>
                            <p class="card-sub">Address, email, phone and social media links.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <x-input name="website_footer_contact_address" label="ঠিকানা (Address)" :value="$v('website_footer_contact_address')" placeholder="খুলনা, বাংলাদেশ" />
                            <x-input name="website_footer_contact_email" label="ইমেইল (Email)" type="email" :value="$v('website_footer_contact_email')" placeholder="info@school.edu" />
                            <x-input name="website_footer_contact_phone" label="ফোন (Phone)" type="tel" :value="$v('website_footer_contact_phone')" placeholder="+880 1xxx xxxxxx" />
                        </div>
                    </div>
                </div>

                {{-- Social media --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Social media links</h3>
                            <p class="card-sub">These icons appear under the যোগাযোগ column of the footer, wrapped across as many rows as needed, using each network's official brand colour.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div id="social-rows" class="stack" style="gap:10px">
                            @foreach ($socialRows as $i => $row)
                                <div class="social-row" style="display:grid;grid-template-columns:minmax(140px,200px) minmax(0,1fr) auto;gap:10px;align-items:end">
                                    <label class="field">
                                        <span class="field-label">Network</span>
                                        <select class="select" name="social_links[{{ $i }}][network]">
                                            <option value="">Select network</option>
                                            @foreach ($socialNetworks as $key => $meta)
                                                <option value="{{ $key }}" @selected(($row['network'] ?? '') === $key)>{{ $meta['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="field">
                                        <span class="field-label">URL</span>
                                        <input class="input" type="text" name="social_links[{{ $i }}][url]" value="{{ $row['url'] ?? '' }}" placeholder="https://facebook.com/yourpage">
                                    </label>
                                    <button type="button" class="btn btn-secondary btn-sm" data-social-remove>Remove</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-outline" id="social-add" style="margin-top:12px">
                            <x-icon name="plus" :size="15" /> Add social link
                        </button>
                        <span class="field-hint">Networks: {{ collect($socialNetworks)->pluck('label')->implode(', ') }}.</span>
                    </div>
                </div>

                {{-- Home page map --}}
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">Home page Google Map</h3>
                            <p class="card-sub">Shown in the "যোগাযোগ" block on the homepage.</p>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-grid">
                            <div class="span-2">
                                <x-textarea name="website_map_embed" label="Google Map embed" rows="3" :value="$v('website_map_embed')"
                                    help="Paste the embed code, or just the src URL, from Google Maps → Share → Embed a map. Leave blank to hide the map." />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <x-button type="submit" variant="primary" icon="save" class="btn-block">Save Footer Area Settings</x-button>
                    </div>
                </div>
            </div>

            <div class="stack">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Preview</h3><p class="card-sub">Four-column footer layout.</p></div></div>
                    <div class="card-body">
                        <div style="border-radius:10px;padding:18px;background:#102f50;color:#fff">
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px">
                                <div>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                                        <div class="brand-mark" style="width:30px;height:30px">HS</div>
                                        <strong style="font-size:12px">School</strong>
                                    </div>
                                    <p style="font-size:11px;color:#cfc3e8;line-height:1.5;margin:0">{{ $v('website_footer_about') ?: 'About information appears here.' }}</p>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:12px;margin-bottom:6px">{{ $v('website_footer_institution_title', 'প্রতিষ্ঠান') }}</div>
                                    <div style="font-size:11px;color:#cfc3e8">Menu items…</div>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:12px;margin-bottom:6px">{{ $v('website_footer_links_title', 'গুরুত্বপূর্ণ লিংক') }}</div>
                                    <div style="font-size:11px;color:#cfc3e8">Menu items…</div>
                                </div>
                                <div>
                                    <div style="font-weight:700;font-size:12px;margin-bottom:6px">যোগাযোগ</div>
                                    <div style="font-size:11px;color:#cfc3e8;line-height:1.6">
                                        <div>📍 {{ $v('website_footer_contact_address') ?: 'Address' }}</div>
                                        <div>✉️ {{ $v('website_footer_contact_email') ?: 'Email' }}</div>
                                        <div>📞 {{ $v('website_footer_contact_phone') ?: 'Phone' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Menu managers live outside the settings form so the forms never nest. --}}
    <div class="stack" style="margin-top:16px">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Column 2 — প্রতিষ্ঠান menu items</h3>
                    <p class="card-sub">Items shown under the প্রতিষ্ঠান heading.</p>
                </div>
                <x-button :href="route('website-menus.index')" variant="secondary" size="sm" icon="settings">Menu builder</x-button>
            </div>
            <div class="card-body">
                @include('admin.website._menu-manager', ['menu' => $institutionMenu, 'location' => 'footer_institution', 'defaultName' => 'Footer — Institution'])
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Column 3 — গুরুত্বপূর্ণ লিংক menu items</h3>
                    <p class="card-sub">Items shown under the important links heading.</p>
                </div>
                <x-button :href="route('website-menus.index')" variant="secondary" size="sm" icon="settings">Menu builder</x-button>
            </div>
            <div class="card-body">
                @include('admin.website._menu-manager', ['menu' => $linksMenu, 'location' => 'footer_links', 'defaultName' => 'Footer — Important links'])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const rows = document.getElementById('social-rows');
            const add = document.getElementById('social-add');
            if (!rows || !add) return;

            const networks = @json($socialNetworks);
            const options = () => '<option value="">Select network</option>'
                + Object.entries(networks).map(([key, meta]) => '<option value="' + key + '">' + meta.label + '</option>').join('');

            add.addEventListener('click', () => {
                const index = 'n' + Date.now();
                const row = document.createElement('div');
                row.className = 'social-row';
                row.style.cssText = 'display:grid;grid-template-columns:minmax(140px,200px) minmax(0,1fr) auto;gap:10px;align-items:end';
                row.innerHTML = '<label class="field"><span class="field-label">Network</span>'
                    + '<select class="select" name="social_links[' + index + '][network]">' + options() + '</select></label>'
                    + '<label class="field"><span class="field-label">URL</span>'
                    + '<input class="input" type="text" name="social_links[' + index + '][url]" placeholder="https://facebook.com/yourpage"></label>'
                    + '<button type="button" class="btn btn-secondary btn-sm" data-social-remove>Remove</button>';
                rows.appendChild(row);
                row.querySelector('select')?.focus();
            });

            rows.addEventListener('click', (event) => {
                if (event.target.closest('[data-social-remove]')) {
                    event.target.closest('.social-row')?.remove();
                }
            });
        })();
    </script>
@endpush