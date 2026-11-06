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
        'school_name', 'school_tagline', 'school_shift', 'school_established_year', 'school_address', 'school_phone', 'school_email',
        'school_website', 'currency_symbol', 'currency_code', 'locale', 'timezone', 'bangla_number', 'font_bn', 'font_en', 'custom_font_path',
        'receipt_footer', 'due_reminder_days', 'fee_allocation_order', 'whatsapp_provider', 'whatsapp_api_url',
        'whatsapp_phone_number_id', 'whatsapp_template_name', 'whatsapp_language', 'backup_notes',
        'website_name_bn', 'website_name_en', 'website_tagline', 'website_logo_mark',
        'website_facebook', 'website_x', 'website_youtube', 'allow_co_teaching',
        'enable_date_display', 'date_calendar', 'date_language', 'date_show_weekday',
        'date_show_bangla', 'date_show_bangla_year', 'date_show_english',
        'date_show_english_year', 'date_separator',
        'report_developer_name', 'report_developer_website', 'report_developer_email', 'report_developer_phone',
    ];

    private const PRINT_FOOTER_KEYS = [
        'report_developer_name', 'report_developer_website', 'report_developer_email', 'report_developer_phone',
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
        $isSuperAdmin = $request->user()?->isSuperAdmin() ?? false;
        if (! $isSuperAdmin && $request->hasAny(self::PRINT_FOOTER_KEYS)) {
            abort(403, 'Only the super administrator can change print footer information.');
        }

        $rules = [
            'school_name' => ['required', 'string', 'max:150'],
            'school_tagline' => ['nullable', 'string', 'max:180'],
            'school_shift' => ['sometimes', 'required', 'string', 'max:80'],
            'school_established_year' => ['nullable', 'integer', 'min:1800', 'max:'.now()->year],
            'school_address' => ['nullable', 'string', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:40'],
            'school_email' => ['nullable', 'email', 'max:255'],
            'school_website' => ['nullable', 'url', 'max:255'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'currency_code' => ['required', 'string', 'max:10'],
            'locale' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'timezone'],
            'bangla_number' => ['nullable', 'boolean'],
            'font_bn' => ['nullable', 'string', 'max:80'],
            'font_en' => ['nullable', 'string', 'max:80'],
            'custom_font_path' => ['nullable', 'string', 'max:255'],
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
        ];

        if ($isSuperAdmin) {
            $rules += [
                'report_developer_name' => ['sometimes', 'required', 'string', 'max:150'],
                'report_developer_website' => ['nullable', 'url:http,https', 'max:255'],
                'report_developer_email' => ['nullable', 'email', 'max:255'],
                'report_developer_phone' => ['nullable', 'string', 'max:40'],
            ];
        }

        $data = $request->validate($rules);

        // Handle custom font upload.
        if ($request->hasFile('custom_font')) {
            $file = $request->file('custom_font');
            $dir = public_path('fonts/custom');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $filename = 'custom-'.time().'.'.$file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $data['custom_font_path'] = 'fonts/custom/'.$filename;
        } else {
            // Keep existing path if no new file uploaded.
            $data['custom_font_path'] = school_setting('custom_font_path');
        }

        $token = $data['whatsapp_token'] ?? null;
        unset($data['whatsapp_token']);

        $groups = [
            'general' => ['school_name', 'school_tagline', 'school_shift', 'school_established_year', 'school_address', 'school_phone', 'school_email', 'school_website'],
            'locale' => ['currency_symbol', 'currency_code', 'locale', 'timezone', 'bangla_number', 'font_bn', 'font_en', 'custom_font_path'],
            'fees' => ['receipt_footer', 'due_reminder_days', 'fee_allocation_order'],
            'whatsapp' => ['whatsapp_provider', 'whatsapp_api_url', 'whatsapp_phone_number_id', 'whatsapp_template_name', 'whatsapp_language'],
            'system' => ['backup_notes'],
            'website' => ['website_name_bn', 'website_name_en', 'website_tagline', 'website_logo_mark', 'website_facebook', 'website_x', 'website_youtube', 'allow_co_teaching'],
            'date' => [
                'enable_date_display', 'date_calendar', 'date_language', 'date_show_weekday',
                'date_show_bangla', 'date_show_bangla_year', 'date_show_english',
                'date_show_english_year', 'date_separator',
            ],
            'print' => self::PRINT_FOOTER_KEYS,
        ];

        foreach ($groups as $group => $keys) {
            foreach ($keys as $key) {
                if ($group === 'print' && (! $isSuperAdmin || ! array_key_exists($key, $data))) {
                    continue;
                }

                if ($key === 'school_shift' && ! array_key_exists($key, $data)) {
                    continue;
                }

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
            [
                'school_name' => $data['school_name'],
                'whatsapp_provider' => $data['whatsapp_provider'],
                'token_changed' => filled($token),
                'print_developer_info_updated' => $isSuperAdmin && collect(self::PRINT_FOOTER_KEYS)->contains(fn ($key) => array_key_exists($key, $data)),
            ]
        );

        return back()->with('success', 'Settings saved.');
    }
}
