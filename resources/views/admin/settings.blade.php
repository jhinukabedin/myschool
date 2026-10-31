@extends('layouts.app')

@section('title', 'Settings')

@php
    $allSettings = $settings->flatten();
    $setting = fn (string $key, mixed $default = null) => old($key, $allSettings->firstWhere('key', $key)?->value ?? $default);
    $timezones = \DateTimeZone::listIdentifiers();

    $dateBoolean = fn (string $key, bool $default = true): bool => filter_var(
        $setting($key, $default ? '1' : '0'),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    ) ?? $default;
    $dateCalendar = (string) $setting('date_calendar', 'bangla');
    $dateCalendar = in_array($dateCalendar, ['bangla', 'english', 'both'], true) ? $dateCalendar : 'bangla';
@endphp

@section('content')
    <x-page-header eyebrow="Admin" title="School Settings" subtitle="School identity, currency, fee reminders, WhatsApp credentials and operational notes.">
        <x-slot:actions>
            <x-button :href="route('admin.index')" variant="secondary" icon="arrow-left">Administration</x-button>
        </x-slot:actions>
    </x-page-header>


    @can('settings.manage')
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            @method('PUT')

            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">School Profile</h3><p class="card-sub">Appears on receipts, result sheets and salary slips</p></div></div>
                    <div class="form-section">
                        <div class="stack">
                            <x-input name="school_name" label="School name" :value="$setting('school_name')" required />
                            <x-input name="school_tagline" label="Tagline" :value="$setting('school_tagline')" />
                            <x-input name="school_address" label="Address" :value="$setting('school_address')" />
                            <div class="form-grid">
                                <x-input name="school_phone" label="Phone" :value="$setting('school_phone')" />
                                <x-input name="school_email" label="Email" type="email" :value="$setting('school_email')" />
                            </div>
                            <x-input name="school_website" label="Website" type="url" :value="$setting('school_website')" placeholder="https://example.com" />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Locale & Currency</h3><p class="card-sub">Used by money formatting and date display</p></div></div>
                    <div class="form-section">
                        <div class="stack">
                            <div class="form-grid">
                                <x-input name="currency_symbol" label="Currency symbol" :value="$setting('currency_symbol')" required maxlength="10" />
                                <x-input name="currency_code" label="Currency code" :value="$setting('currency_code')" required maxlength="10" />
                            </div>
                            <div class="form-grid">
                                <x-input name="locale" label="Locale" :value="$setting('locale')" required maxlength="10" />
                                <x-select name="timezone" label="Timezone" :options="array_combine($timezones, $timezones)" :selected="$setting('timezone')" required />
                            </div>
                            <div class="info-box">Changing the currency symbol updates every new money display. Existing financial amounts are stored as decimals and are not rewritten.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:16px">
                <div class="card-header"><div><h3 class="card-title">Public Website Identity</h3><p class="card-sub">Shown on the public website. Leave blank to use the built-in defaults.</p></div></div>
                <div class="form-section">
                    <div class="form-grid">
                        <x-input name="website_name_bn" label="School name (Bangla)" :value="$setting('website_name_bn')" placeholder="আদর্শ উচ্চ বিদ্যালয়" />
                        <x-input name="website_name_en" label="School name (English)" :value="$setting('website_name_en')" placeholder="ADARSHA HIGH SCHOOL" />
                        <x-input name="website_tagline" label="Website tagline" :value="$setting('website_tagline')" placeholder="শিক্ষা • শৃঙ্খলা • নৈতিকতা • আধুনিকতা" />
                        <x-input name="website_logo_mark" label="Logo initials" :value="$setting('website_logo_mark', 'HS')" maxlength="4" />
                        <x-input name="website_facebook" label="Facebook URL" type="url" :value="$setting('website_facebook')" />
                        <x-input name="website_x" label="X / Twitter URL" type="url" :value="$setting('website_x')" />
                        <x-input name="website_youtube" label="YouTube URL" type="url" :value="$setting('website_youtube')" />
                        <div class="span-2">
                            <input type="hidden" name="allow_co_teaching" value="0">
                            <label class="checkbox">
                                <input type="checkbox" name="allow_co_teaching" value="1" @checked(old('allow_co_teaching', $setting('allow_co_teaching')) == 1)>
                                <span>Allow co-teaching (more than one active teacher per class/section/subject)</span>
                            </label>
                        </div>
                    </div>
                    <div class="info-box" style="margin-top:10px">Website address, phone and email use the School Profile above so receipts and the public site stay consistent.</div>
                </div>
            </div>

            <div class="card" style="margin-top:16px"
                 x-data="{ enabled: @js($dateBoolean('enable_date_display')), calendar: @js($dateCalendar) }">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Date &amp; Calendar Settings</h3>
                        <p class="card-sub">Bangla and English date shown in the dashboard topbar, portal header and public website utility bar.</p>
                    </div>
                </div>
                <div class="form-section">
                    <div class="stack">
                        <div>
                            <input type="hidden" name="enable_date_display" value="0">
                            <label class="checkbox">
                                <input type="checkbox" name="enable_date_display" value="1" x-model="enabled" @checked($dateBoolean('enable_date_display'))>
                                <span>Show the date in dashboard and portal headers</span>
                            </label>
                        </div>

                        <div x-show="enabled" x-cloak class="stack">
                            <div class="form-grid">
                                <x-select name="date_calendar" label="Calendar mode" :options="['bangla' => 'Bangla calendar', 'english' => 'English calendar', 'both' => 'Both calendars']" :selected="old('date_calendar', $dateCalendar)" required x-model="calendar" />
                                <x-select name="date_language" label="Language & numerals" :options="['bangla' => 'Bangla', 'english' => 'English']" :selected="$setting('date_language', 'bangla')" required />
                            </div>

                            <div class="form-grid">
                                <div>
                                    <input type="hidden" name="date_show_weekday" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="date_show_weekday" value="1" @checked($dateBoolean('date_show_weekday'))>
                                        <span>Show the weekday (once, at the beginning when both calendars are used)</span>
                                    </label>
                                </div>
                                <div x-show="calendar === 'both'" x-cloak>
                                    <x-input name="date_separator" label="Date separator" :value="$setting('date_separator', ' | ')" maxlength="20" help="Used between the Bangla and English dates. Default: space, pipe, space." />
                                </div>
                            </div>

                            <div class="form-grid" x-show="calendar === 'both'" x-cloak>
                                <div>
                                    <input type="hidden" name="date_show_bangla" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="date_show_bangla" value="1" @checked($dateBoolean('date_show_bangla'))>
                                        <span>Show the Bangla date</span>
                                    </label>
                                </div>
                                <div>
                                    <input type="hidden" name="date_show_english" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="date_show_english" value="1" @checked($dateBoolean('date_show_english'))>
                                        <span>Show the English date</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div x-show="calendar !== 'english'" x-cloak>
                                    <input type="hidden" name="date_show_bangla_year" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="date_show_bangla_year" value="1" @checked($dateBoolean('date_show_bangla_year'))>
                                        <span>Show the Bangla year (বঙ্গাব্দ / Bangabda)</span>
                                    </label>
                                </div>
                                <div x-show="calendar !== 'bangla'" x-cloak>
                                    <input type="hidden" name="date_show_english_year" value="0">
                                    <label class="checkbox">
                                        <input type="checkbox" name="date_show_english_year" value="1" @checked($dateBoolean('date_show_english_year'))>
                                        <span>Show the English (Gregorian) year</span>
                                    </label>
                                </div>
                            </div>

                            <div class="info-box">The Bangla date uses the revised Bangladesh calendar (2019): the year starts on 14 April, Falgun has 29 or 30 days and Chaitra has 30 days. The language setting controls weekday, month names, year suffix and numerals together, so Bangla and English numerals are never mixed.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid-2" style="margin-top:16px">
                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">Fees</h3><p class="card-sub">Receipt and reminder configuration</p></div></div>
                    <div class="form-section">
                        <div class="stack">
                            <x-textarea name="receipt_footer" label="Receipt footer" :value="$setting('receipt_footer')" rows="3" />
                            <x-input name="due_reminder_days" label="Due reminder window (days)" type="number" min="1" max="90" :value="$setting('due_reminder_days', 7)" required help="Fees due within this many days are included in reminder lists." />
                            <x-select name="fee_allocation_order" label="Quick-collect allocation order" :options="['oldest' => 'Oldest dues first', 'newest' => 'Newest dues first']" :selected="$setting('fee_allocation_order', 'oldest')" required help="Applies to lump-sum quick collection. Manual collection always uses the lines you enter." />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><div><h3 class="card-title">System Notes</h3><p class="card-sub">Operational reminders stored with the school</p></div></div>
                    <div class="form-section">
                        <div class="stack">
                            <x-textarea name="backup_notes" label="Backup & operations notes" :value="$setting('backup_notes')" rows="5" />
                            <div class="info-box">Use this area to record the backup location and responsible person. Never store production passwords here.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:16px">
                <div class="card-header">
                    <div><h3 class="card-title">WhatsApp Reminders</h3><p class="card-sub">Choose a real provider. Unconfigured sending fails loudly instead of reporting a fake success.</p></div>
                    <x-badge :color="$hasWhatsAppToken ? 'success' : 'warning'">{{ $hasWhatsAppToken ? 'Token stored' : 'No token' }}</x-badge>
                </div>
                <div class="form-section">
                    <div class="form-grid">
                        <x-select name="whatsapp_provider" label="Provider" :options="['none' => 'Disabled', 'meta' => 'Meta WhatsApp Cloud API', 'generic' => 'Generic HTTP gateway']" :selected="$setting('whatsapp_provider', 'none')" required />
                        <x-input name="whatsapp_api_url" label="API base URL" type="url" :value="$setting('whatsapp_api_url')" help="Meta: https://graph.facebook.com/v20.0. Generic: full endpoint URL." />
                        <x-input name="whatsapp_phone_number_id" label="Phone number ID" :value="$setting('whatsapp_phone_number_id')" help="Required for the Meta Cloud API." />
                        <x-input name="whatsapp_template_name" label="Template name" :value="$setting('whatsapp_template_name')" help="Required for the Meta Cloud API." />
                        <x-input name="whatsapp_language" label="Template language" :value="$setting('whatsapp_language', 'en')" />
                        <x-input name="whatsapp_token" label="Access token" type="password" autocomplete="new-password" :help="$hasWhatsAppToken ? 'A token is already stored. Leave blank to keep it.' : 'Paste a valid token. It is stored in the database and never displayed again.'" />
                    </div>
                    <div class="warning-box" style="margin-top:14px">
                        Sending is only possible after a provider, token and required identifiers are configured. The generic gateway receives
                        <span class="mono">to</span>, <span class="mono">message</span> and <span class="mono">template</span> fields as JSON with an
                        <span class="mono">Authorization: Bearer</span> header.
                    </div>
                </div>
                <div class="form-section" style="display:flex;justify-content:flex-end">
                    <x-button type="submit" variant="primary" icon="save">Save settings</x-button>
                </div>
            </div>
        </form>
    @else
        <div class="card">
            <div class="card-body">
                <div class="info-box">You have read-only access to settings. Contact an administrator to make changes.</div>
            </div>
        </div>
    @endcan
@endsection
