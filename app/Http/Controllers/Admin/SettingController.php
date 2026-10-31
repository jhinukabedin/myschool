<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const KEYS = [
        'school_name', 'school_tagline', 'school_address', 'school_phone', 'school_email',
        'school_website', 'currency_symbol', 'currency_code', 'locale', 'timezone',
        'receipt_footer', 'due_reminder_days', 'fee_allocation_order', 'whatsapp_provider', 'whatsapp_api_url',
        'whatsapp_phone_number_id', 'whatsapp_template_name', 'whatsapp_language', 'backup_notes',
        'website_name_bn', 'website_name_en', 'website_tagline', 'website_logo_mark',
        'website_facebook', 'website_x', 'website_youtube', 'allow_co_teaching',
        'enable_date_display', 'date_calendar', 'date_language', 'date_show_weekday',
        'date_show_bangla', 'date_show_bangla_year', 'date_show_english',
        'date_show_english_year', 'date_separator',
    ];

    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        $settings = SchoolSetting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('admin.settings', [
            'settings' => $settings,
            'hasWhatsAppToken' => filled(SchoolSetting::get('whatsapp_token')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:150'],
            'school_tagline' => ['nullable', 'string', 'max:180'],
            'school_address' => ['nullable', 'string', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:40'],
            'school_email' => ['nullable', 'email', 'max:255'],
            'school_website' => ['nullable', 'url', 'max:255'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_code' => ['required', 'string', 'max:10'],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'timezone'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'due_reminder_days' => ['required', 'integer', 'min:1', 'max:90'],
            'fee_allocation_order' => ['required', 'in:oldest,newest'],
            'whatsapp_provider' => ['required', 'in:none,meta,generic'],
            'whatsapp_api_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:120'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:120'],
            'whatsapp_language' => ['nullable', 'string', 'max:10'],
            'whatsapp_token' => ['nullable', 'string', 'max:2000'],
            'backup_notes' => ['nullable', 'string', 'max:2000'],
            'website_name_bn' => ['nullable', 'string', 'max:150'],
            'website_name_en' => ['nullable', 'string', 'max:150'],
            'website_tagline' => ['nullable', 'string', 'max:200'],
            'website_logo_mark' => ['nullable', 'string', 'max:4'],
            'website_facebook' => ['nullable', 'url', 'max:255'],
            'website_x' => ['nullable', 'url', 'max:255'],
            'website_youtube' => ['nullable', 'url', 'max:255'],
            'allow_co_teaching' => ['nullable', 'boolean'],
            'enable_date_display' => ['nullable', 'boolean'],
            'date_calendar' => ['required', 'in:bangla,english,both'],
            'date_language' => ['required', 'in:bangla,english'],
            'date_show_weekday' => ['nullable', 'boolean'],
            'date_show_bangla' => ['nullable', 'boolean'],
            'date_show_bangla_year' => ['nullable', 'boolean'],
            'date_show_english' => ['nullable', 'boolean'],
            'date_show_english_year' => ['nullable', 'boolean'],
            'date_separator' => ['nullable', 'string', 'max:20'],
        ]);

        $token = $data['whatsapp_token'] ?? null;
        unset($data['whatsapp_token']);

        $groups = [
            'general' => ['school_name', 'school_tagline', 'school_address', 'school_phone', 'school_email', 'school_website'],
            'locale' => ['currency_symbol', 'currency_code', 'locale', 'timezone'],
            'fees' => ['receipt_footer', 'due_reminder_days', 'fee_allocation_order'],
            'whatsapp' => ['whatsapp_provider', 'whatsapp_api_url', 'whatsapp_phone_number_id', 'whatsapp_template_name', 'whatsapp_language'],
            'system' => ['backup_notes'],
            'website' => ['website_name_bn', 'website_name_en', 'website_tagline', 'website_logo_mark', 'website_facebook', 'website_x', 'website_youtube', 'allow_co_teaching'],
            'date' => [
                'enable_date_display', 'date_calendar', 'date_language', 'date_show_weekday',
                'date_show_bangla', 'date_show_bangla_year', 'date_show_english',
                'date_show_english_year', 'date_separator',
            ],
        ];

        foreach ($groups as $group => $keys) {
            foreach ($keys as $key) {
                SchoolSetting::put($key, $data[$key] ?? null, $group);
            }
        }

        if (filled($token)) {
            SchoolSetting::put('whatsapp_token', $token, 'whatsapp');
        }

        $this->audit->record(
            'settings_updated',
            'Administration',
            'School settings updated',
            null,
            null,
            ['school_name' => $data['school_name'], 'whatsapp_provider' => $data['whatsapp_provider'], 'token_changed' => filled($token)]
        );

        return back()->with('success', 'Settings saved.');
    }
}
