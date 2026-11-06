<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Examination;
use App\Models\Media;
use App\Models\Notice;
use App\Models\SchoolSetting;
use App\Models\WebsiteContent;
use App\Models\WebsiteMenu;
use App\Services\AuditLogService;
use App\Services\HomeSections;
use App\Services\SocialLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteAdminController extends Controller
{
    /**
     * Defaults are kept here as well as in the seeder so existing databases
     * receive sensible values without rewriting configured settings.
     */
    public const DEFAULTS = [
        'website_name_bn' => 'আদর্শ উচ্চ বিদ্যালয়',
        'website_name_en' => 'ADARSHA HIGH SCHOOL',
        'website_tagline' => 'শিক্ষা • শৃঙ্খলা • নৈতিকতা • আধুনিকতা',
        'website_logo_mark' => 'HS',
        'website_topbar_text' => 'শিক্ষা • শৃঙ্খলা • নৈতিকতা • আধুনিকতা',
        'website_header_cta_label' => 'ভর্তি আবেদন',
        'website_header_cta_url' => '/admission/apply',
        'website_footer_about' => 'শিক্ষার্থীদের জ্ঞান, নৈতিকতা ও মানবিক মূল্যবোধে গড়ে তোলার অঙ্গীকার।',
        'website_footer_copyright' => '© :year আদর্শ উচ্চ বিদ্যালয় • সর্বস্বত্ব সংরক্ষিত',
        'website_footer_note' => 'সাইটটি বিদ্যালয়ের অফিস কর্তৃক পরিচালিত।',
        'website_header_style' => 'classic',
        'website_footer_style' => 'classic',
        'website_theme' => 'navy',
        'website_show_topbar' => '1',
        'website_show_date' => '1',
        'website_show_login_menu' => '1',
        'website_header_login_label' => 'Student Login',
        'website_header_login_border_radius' => '10',
        'website_header_date_position' => 'left',
        'website_footer_institution_title' => 'প্রতিষ্ঠান',
        'website_footer_links_title' => 'গুরুত্বপূর্ণ লিংক',
        'website_footer_contact_address' => '',
        'website_footer_contact_email' => '',
        'website_footer_contact_phone' => '',
        'website_hero_tagline' => 'শিক্ষা • শৃঙ্খলা • নৈতিকতা • আধুনিকতা',
        'website_hero_title' => 'আদর্শ উচ্চ বিদ্যালয়ে স্বাগতম',
        'website_hero_description' => 'জ্ঞান, নৈতিকতা ও মানবিক মূল্যবোধে গড়ে তোলা হয় প্রতিটি শিক্ষার্থীকে।',
        'website_hero_menu_name' => 'ভর্তি আবেদন',
        'website_hero_menu_link' => '/admission/apply',
        'website_facebook' => '',
        'website_x' => '',
        'website_youtube' => '',
        'website_social_links' => '',
        'website_map_embed' => '',
        'website_home_sections' => '',
        'website_result_search_enabled' => '1',
        'website_result_title' => 'রেজাল্ট',
        'website_result_subtitle' => 'শিক্ষার্থী আইডি দিয়ে ফলাফল খুঁজুন',
        'website_result_help' => 'শিক্ষার্থীর আইডি নম্বর লিখুন (যেমন: 2601001)।',
        'website_result_empty_message' => 'এই মুহূর্তে কোনো রেজাল্ট পাবলিশ হয়নি',
    ];

    public const HEADER_STYLES = [
        'classic' => 'Classic blue',
        'centered' => 'Centered crest',
        'split' => 'Split banner',
        'minimal' => 'Minimal compact',
    ];

    public const FOOTER_STYLES = [
        'classic' => 'Classic four columns',
        'columns' => 'Wide columns',
        'compact' => 'Compact bar',
        'accent' => 'Accent panel',
    ];

    public const THEMES = [
        'navy' => 'Navy & gold',
        'emerald' => 'Emerald & sand',
        'maroon' => 'Maroon & cream',
        'royal' => 'Royal purple',
    ];

    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        $counts = WebsiteContent::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('admin.website.dashboard', [
            'counts' => $counts,
            'sections' => WebsiteContentController::SECTIONS,
            'published' => WebsiteContent::query()->where('status', 'published')->count(),
            'draft' => WebsiteContent::query()->where('status', 'draft')->count(),
            'latest' => WebsiteContent::query()->with('creator')->latest('updated_at')->limit(8)->get(),
            'newMessages' => ContactMessage::query()->where('status', 'new')->count(),
            'hasHeaderMenu' => WebsiteMenu::query()->where('location', 'header')->exists(),
            'hasFooterMenu' => WebsiteMenu::query()->where('location', 'footer')->exists(),
            'homeSectionOptions' => HomeSections::SECTIONS,
            'homeSectionsEnabled' => HomeSections::enabled(school_setting('website_home_sections')),
        ]);
    }

    public function design(): View
    {
        $settings = [];

        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = school_setting($key, $default);
        }

        return view('admin.website.design', [
            'settings' => $settings,
            'logoPath' => school_setting('website_logo_path'),
            'logoUrl' => school_setting('website_logo_path') ? asset('storage/'.school_setting('website_logo_path')) : null,
            'headerStyles' => self::HEADER_STYLES,
            'footerStyles' => self::FOOTER_STYLES,
            'themes' => self::THEMES,
        ]);
    }

    public function updateDesign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_name_bn' => ['required', 'string', 'max:150'],
            'website_name_en' => ['required', 'string', 'max:150'],
            'website_tagline' => ['nullable', 'string', 'max:200'],
            'website_logo_mark' => ['nullable', 'string', 'max:4'],
            'website_topbar_text' => ['nullable', 'string', 'max:180'],
            'website_header_cta_label' => ['nullable', 'string', 'max:60'],
            'website_header_cta_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', (string) $value)) {
                    $fail('The header button link must be a relative path or an http(s), mailto or tel URL.');
                }
            }],
            'website_footer_about' => ['nullable', 'string', 'max:500'],
            'website_footer_copyright' => ['nullable', 'string', 'max:200'],
            'website_footer_note' => ['nullable', 'string', 'max:200'],
            'website_facebook' => ['nullable', 'url', 'max:255'],
            'website_x' => ['nullable', 'url', 'max:255'],
            'website_youtube' => ['nullable', 'url', 'max:255'],
            'website_header_style' => ['required', Rule::in(array_keys(self::HEADER_STYLES))],
            'website_footer_style' => ['required', Rule::in(array_keys(self::FOOTER_STYLES))],
            'website_theme' => ['required', Rule::in(array_keys(self::THEMES))],
            'website_show_topbar' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $logo = $request->file('logo');
        $removeLogo = $request->boolean('remove_logo');
        unset($data['logo'], $data['remove_logo']);

        $before = collect(array_keys(self::DEFAULTS))
            ->mapWithKeys(fn (string $key) => [$key => school_setting($key)])
            ->all();

        $oldLogo = school_setting('website_logo_path');

        if ($logo) {
            $data['website_logo_path'] = $logo->store('website/logo', 'public');

            if ($oldLogo && $oldLogo !== $data['website_logo_path']) {
                $this->deleteLogoIfUnused($oldLogo);
            }
        } elseif ($removeLogo && $oldLogo) {
            $data['website_logo_path'] = null;
            $this->deleteLogoIfUnused($oldLogo);
        }

        $data['website_show_topbar'] = $request->boolean('website_show_topbar') ? '1' : '0';

        foreach ($data as $key => $value) {
            SchoolSetting::put($key, $value, 'website');
        }

        $this->audit->record(
            'website_design_updated',
            'Website',
            'Website design, logo, header and footer settings updated',
            null,
            $before,
            collect(array_keys(self::DEFAULTS))->mapWithKeys(fn (string $key) => [$key => school_setting($key)])->all()
                + ['logo_changed' => (bool) $logo, 'logo_removed' => $removeLogo]
        );

        return back()->with('success', 'Website design saved. The public site now uses the selected header, footer, logo and content text.');
    }

    /**
     * Show Header Area settings
     */
    public function header(): View
    {
        $v = fn (string $key, mixed $default = null) => old($key, school_setting($key, $default));

        return view('admin.website.header', [
            'settings' => [
                'website_show_date' => $v('website_show_date', '1'),
                'website_header_date_position' => $v('website_header_date_position', 'left'),
                'website_show_login_menu' => $v('website_show_login_menu', '1'),
                'website_header_login_label' => $v('website_header_login_label', 'Student Login'),
                'website_header_login_border_radius' => $v('website_header_login_border_radius', '10'),
            ],
            'headerMenu' => WebsiteMenu::query()
                ->where('location', 'header')
                ->with(['rootItems.children'])
                ->first(),
            'loginMenu' => WebsiteMenu::query()
                ->where('location', 'login')
                ->with(['rootItems'])
                ->first(),
            'menuLocations' => ['header' => 'Header navigation', 'footer' => 'Footer links', 'secondary' => 'Secondary navigation'],
        ]);
    }
    
    /**
     * Show Footer Area settings
     */
    public function footer(): View
    {
        $v = fn (string $key, mixed $default = null) => old($key, school_setting($key, $default));

        $logoPath = school_setting('website_logo_path');

        return view('admin.website.footer', [
            'settings' => [
                'website_footer_about' => $v('website_footer_about', ''),
                'website_footer_institution_title' => $v('website_footer_institution_title', 'প্রতিষ্ঠান'),
                'website_footer_links_title' => $v('website_footer_links_title', 'গুরুত্বপূর্ণ লিংক'),
                'website_footer_contact_address' => $v('website_footer_contact_address', ''),
                'website_footer_contact_email' => $v('website_footer_contact_email', ''),
                'website_footer_contact_phone' => $v('website_footer_contact_phone', ''),
                'website_map_embed' => $v('website_map_embed', ''),
            ],
            'socialRows' => SocialLinks::parse(
                school_setting('website_social_links'),
                [
                    'facebook' => school_setting('website_facebook'),
                    'x' => school_setting('website_x'),
                    'youtube' => school_setting('website_youtube'),
                ]
            ),
            'socialNetworks' => SocialLinks::networks(),
            'logoUrl' => $logoPath ? asset('storage/'.$logoPath) : null,
            'logoMark' => school_setting('website_logo_mark', 'HS'),
            'institutionMenu' => WebsiteMenu::query()->where('location', 'footer_institution')->with(['rootItems'])->first(),
            'linksMenu' => WebsiteMenu::query()->where('location', 'footer_links')->with(['rootItems'])->first(),
        ]);
    }
    
    /**
     * Show Hero Section settings
     */
    public function hero(): View
    {
        $v = fn (string $key, mixed $default = null) => old($key, school_setting($key, $default));
        
        return view('admin.website.hero', [
            'settings' => [
                'website_hero_tagline' => $v('website_hero_tagline', ''),
                'website_hero_title' => $v('website_hero_title', ''),
                'website_hero_description' => $v('website_hero_description', ''),
                'website_hero_menu_name' => $v('website_hero_menu_name', ''),
                'website_hero_menu_link' => $v('website_hero_menu_link', ''),
            ],
        ]);
    }

    /**
     * Update Header settings
     */
    public function updateHeader(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_show_date' => ['nullable', 'boolean'],
            'website_header_date_position' => ['required', 'in:left,center,right'],
            'website_show_login_menu' => ['nullable', 'boolean'],
            'website_header_login_label' => ['required', 'string', 'max:60'],
            'website_header_login_border_radius' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $data['website_show_date'] = $request->boolean('website_show_date') ? '1' : '0';
        $data['website_show_login_menu'] = $request->boolean('website_show_login_menu') ? '1' : '0';
        
        $before = collect(array_keys(self::DEFAULTS))
            ->mapWithKeys(fn (string $key) => [$key => school_setting($key)])
            ->all();
        
        foreach ($data as $key => $value) {
            SchoolSetting::put($key, $value, 'website');
        }
        
        $this->audit->record(
            'website_header_updated',
            'Website',
            'Header settings updated',
            null,
            $before,
            $data
        );
        
        return back()->with('success', 'Header settings saved successfully.');
    }
    
    /**
     * Update Footer settings
     */
    public function updateFooter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_footer_about' => ['nullable', 'string', 'max:800'],
            'website_footer_institution_title' => ['required', 'string', 'max:100'],
            'website_footer_links_title' => ['required', 'string', 'max:100'],
            'website_footer_contact_address' => ['nullable', 'string', 'max:200'],
            'website_footer_contact_email' => ['nullable', 'email', 'max:255'],
            'website_footer_contact_phone' => ['nullable', 'string', 'max:30'],
            'website_map_embed' => ['nullable', 'string', 'max:2000'],
            'social_links' => ['nullable', 'array', 'max:20'],
            'social_links.*.network' => ['nullable', 'string', 'max:50'],
            'social_links.*.url' => ['nullable', 'string', 'max:500'],
        ]);

        $rows = $request->input('social_links', []);
        unset($data['social_links']);

        $links = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $network = strtolower(trim((string) ($row['network'] ?? '')));
                $url = $this->cleanSocialUrl((string) ($row['url'] ?? ''));

                if ($network === '' || $url === null || ! array_key_exists($network, SocialLinks::networks())) {
                    continue;
                }

                $links[] = ['network' => $network, 'url' => $url];
            }
        }

        $data['website_social_links'] = json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $before = collect(array_keys(self::DEFAULTS))
            ->mapWithKeys(fn (string $key) => [$key => school_setting($key)])
            ->all();

        foreach ($data as $key => $value) {
            SchoolSetting::put($key, $value, 'website');
        }

        $this->audit->record(
            'website_footer_updated',
            'Website',
            'Footer settings updated',
            null,
            $before,
            $data
        );

        return back()->with('success', 'Footer settings saved successfully.');
    }

    /**
     * Keep only safe social link targets: relative paths or http(s), mailto
     * and tel URLs.
     */
    private function cleanSocialUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        return preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $url) ? $url : null;
    }

    /**
     * Which homepage blocks are visible, saved from the website dashboard.
     */
    public function updateHomeSections(Request $request): RedirectResponse
    {
        $selected = array_values(array_intersect(
            HomeSections::all(),
            array_map('strval', (array) $request->input('sections', []))
        ));

        $before = school_setting('website_home_sections');

        SchoolSetting::put('website_home_sections', json_encode($selected, JSON_UNESCAPED_SLASHES), 'website');

        $this->audit->record(
            'website_home_sections_updated',
            'Website',
            'Homepage section visibility updated',
            null,
            ['sections' => $before],
            ['sections' => $selected]
        );

        return back()->with('success', 'Homepage section visibility saved.');
    }

    /**
     * Read-only notice overview for the website area, with quick links into the
     * communication module where notices are created and published.
     */
    public function notice(): View
    {
        return view('admin.website.notice', [
            'notices' => Notice::query()->latest('created_at')->paginate(15),
        ]);
    }
    
    /**
     * Update Hero Section settings
     */
    public function updateHero(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_hero_tagline' => ['nullable', 'string', 'max:100'],
            'website_hero_title' => ['nullable', 'string', 'max:200'],
            'website_hero_description' => ['nullable', 'string', 'max:500'],
            'website_hero_menu_name' => ['nullable', 'string', 'max:100'],
            'website_hero_menu_link' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/|#)/i'],
        ]);
        
        $before = collect(array_keys(self::DEFAULTS))
            ->mapWithKeys(fn (string $key) => [$key => school_setting($key)])
            ->all();
        
        foreach ($data as $key => $value) {
            SchoolSetting::put($key, $value, 'website');
        }
        
        $this->audit->record(
            'website_hero_updated',
            'Website',
            'Hero section settings updated',
            null,
            $before,
            $data
        );
        
        return back()->with('success', 'Hero section settings saved successfully.');
    }
    
    /**
     * Manage Sliders
     */
    public function sliders(): View
    {
        $sliders = WebsiteContent::query()
            ->ofType('slider')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.website.sliders', [
            'sliders' => $sliders,
        ]);
    }
    
    /**
     * Manage Featured Menu items
     */
    public function featuredMenu(): View
    {
        return view('admin.website.featured-menu', [
            'menu' => WebsiteMenu::query()->where('location', 'featured')->with(['rootItems'])->first(),
        ]);
    }
    
    /**
     * Sidebar management
     */
    public function sidebar(): View
    {
        return view('admin.website.sidebar', [
            'menu' => WebsiteMenu::query()->where('location', 'sidebar')->with(['rootItems'])->first(),
        ]);
    }

    /**
     * Public result search settings and publishing status.
     */
    public function resultSearch(): View
    {
        $v = fn (string $key, mixed $default = null) => old($key, school_setting($key, $default));

        $exams = Examination::query()
            ->where('status', 'published')
            ->whereNotNull('result_published_at')
            ->with('academicSession')
            ->withCount('resultSnapshots')
            ->orderByDesc('result_published_at')
            ->get();

        return view('admin.website.result-search', [
            'settings' => [
                'website_result_search_enabled' => $v('website_result_search_enabled', '1'),
                'website_result_title' => $v('website_result_title', 'রেজাল্ট'),
                'website_result_subtitle' => $v('website_result_subtitle', 'শিক্ষার্থী আইডি দিয়ে ফলাফল খুঁজুন'),
                'website_result_help' => $v('website_result_help', 'শিক্ষার্থীর আইডি নম্বর লিখুন (যেমন: 2601001)।'),
                'website_result_empty_message' => $v('website_result_empty_message', 'এই মুহূর্তে কোনো রেজাল্ট পাবলিশ হয়নি'),
            ],
            'exams' => $exams,
            'publishedCount' => $exams->sum('result_snapshots_count'),
        ]);
    }

    public function updateResultSearch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_result_search_enabled' => ['nullable', 'boolean'],
            'website_result_title' => ['required', 'string', 'max:100'],
            'website_result_subtitle' => ['nullable', 'string', 'max:200'],
            'website_result_help' => ['nullable', 'string', 'max:250'],
            'website_result_empty_message' => ['required', 'string', 'max:200'],
        ]);

        $data['website_result_search_enabled'] = $request->boolean('website_result_search_enabled') ? '1' : '0';

        $before = collect(array_keys($data))
            ->mapWithKeys(fn (string $key) => [$key => school_setting($key)])
            ->all();

        foreach ($data as $key => $value) {
            SchoolSetting::put($key, $value, 'website');
        }

        $this->audit->record(
            'website_result_search_updated',
            'Website',
            'Public result search settings updated',
            null,
            $before,
            $data
        );

        return back()->with('success', 'Result search settings saved.');
    }

    private function deleteLogoIfUnused(string $path): void
    {
        $usedByContent = WebsiteContent::query()
            ->where(fn ($query) => $query->where('image_path', $path)->orWhere('og_image_path', $path))
            ->exists();
        $usedByMedia = Media::query()->where('path', $path)->exists();

        if (! $usedByContent && ! $usedByMedia) {
            Storage::disk('public')->delete($path);
        }
    }
}
