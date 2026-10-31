<?php

namespace App\Providers;

use App\Models\WebsiteMenu;
use App\Services\DateCalendarService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request keeps the formatted date memoised while the
        // underlying settings keep their own shared cache.
        $this->app->singleton(DateCalendarService::class);
    }

    public function boot(): void
    {
        // Apply the school's configured timezone when it is a valid identifier.
        // The helper is null-safe, so this is skipped before the settings table
        // exists (for example during a fresh migration).
        $timezone = school_setting('timezone');

        if (is_string($timezone) && $timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
            config(['app.timezone' => $timezone]);
        }

        // Register every canonical permission as a gate. Role checks happen in User::hasPermission.
        foreach (config('school.permissions', []) as $slug => $meta) {
            Gate::define($slug, fn ($user) => $user->hasPermission($slug));
        }

        View::composer(['layouts.app', 'layouts.auth', 'layouts.print'], function ($view) {
            $view->with('schoolSettings', [
                'name' => school_setting('school_name', config('app.name')),
                'tagline' => school_setting('school_tagline', 'School Management System'),
                'address' => school_setting('school_address', ''),
                'phone' => school_setting('school_phone', ''),
                'email' => school_setting('school_email', ''),
                'logo' => school_setting('school_logo'),
                'currency' => school_setting('currency_symbol', config('school.currency')),
            ]);
        });

        // Public website identity. Kept separate from the admin school profile so
        // the public site can use its Bengali identity from school-web while
        // receipts keep the configured administrative school name.
        View::composer(['layouts.public', 'website.*'], function ($view) {
            $siteNameBn = school_setting('website_name_bn') ?: 'আদর্শ উচ্চ বিদ্যালয়';
            $logoPath = school_setting('website_logo_path');
            $headerStyle = (string) school_setting('website_header_style', 'classic');
            $footerStyle = (string) school_setting('website_footer_style', 'classic');
            $theme = (string) school_setting('website_theme', 'navy');

            $view->with([
                'siteNameBn' => $siteNameBn,
                'siteNameEn' => school_setting('website_name_en') ?: 'ADARSHA HIGH SCHOOL',
                'siteTagline' => school_setting('website_tagline') ?: 'শিক্ষা • শৃঙ্খলা • নৈতিকতা • আধুনিকতা',
                'logoMark' => school_setting('website_logo_mark') ?: 'HS',
                'siteLogoUrl' => $logoPath ? asset('storage/'.$logoPath) : null,
                'siteTopbarText' => school_setting('website_topbar_text') ?: school_setting('website_tagline') ?: '',
                'siteShowTopbar' => school_setting('website_show_topbar', '1') === '1',
                'siteShowDate' => school_setting('website_show_date', '1') === '1',
                'siteDatePosition' => in_array(school_setting('website_header_date_position', 'left'), ['left', 'center', 'right'], true)
                    ? school_setting('website_header_date_position', 'left')
                    : 'left',
                'siteShowLoginMenu' => school_setting('website_show_login_menu', '1') === '1',
                'siteLoginRadius' => (int) school_setting('website_header_login_border_radius', '10'),
                'siteHeaderCtaLabel' => school_setting('website_header_cta_label') ?: '',
                'siteHeaderCtaUrl' => school_setting('website_header_cta_url') ?: '',
                'siteFooterAbout' => school_setting('website_footer_about') ?: '',
                'siteFooterCopyright' => school_setting('website_footer_copyright') ?: '',
                'siteFooterNote' => school_setting('website_footer_note') ?: '',
                'siteFooterInstitutionTitle' => school_setting('website_footer_institution_title') ?: 'প্রতিষ্ঠান',
                'siteFooterLinksTitle' => school_setting('website_footer_links_title') ?: 'গুরুত্বপূর্ণ লিংক',
                'siteFooterContactAddress' => school_setting('website_footer_contact_address') ?: '',
                'siteFooterContactEmail' => school_setting('website_footer_contact_email') ?: '',
                'siteFooterContactPhone' => school_setting('website_footer_contact_phone') ?: '',
                'siteHeroTagline' => school_setting('website_hero_tagline') ?: '',
                'siteHeroTitle' => school_setting('website_hero_title') ?: '',
                'siteHeroDescription' => school_setting('website_hero_description') ?: '',
                'siteHeroMenuName' => school_setting('website_hero_menu_name') ?: '',
                'siteHeroMenuLink' => school_setting('website_hero_menu_link') ?: '',
                'siteHeaderStyle' => in_array($headerStyle, ['classic', 'centered', 'split', 'minimal'], true) ? $headerStyle : 'classic',
                'siteFooterStyle' => in_array($footerStyle, ['classic', 'columns', 'compact', 'accent'], true) ? $footerStyle : 'classic',
                'siteTheme' => in_array($theme, ['navy', 'emerald', 'maroon', 'royal'], true) ? $theme : 'navy',
                'siteAddress' => school_setting('school_address') ?: '',
                'sitePhone' => school_setting('school_phone') ?: '',
                'siteEmail' => school_setting('school_email') ?: '',
                'siteMetaDescription' => school_setting('website_tagline') ?: ($siteNameBn.' এর অফিসিয়াল ওয়েবসাইট'),
                'socialFacebook' => school_setting('website_facebook'),
                'socialX' => school_setting('website_x'),
                'socialYoutube' => school_setting('website_youtube'),
                'socialLinks' => \App\Services\SocialLinks::parse(
                    school_setting('website_social_links'),
                    [
                        'facebook' => school_setting('website_facebook'),
                        'x' => school_setting('website_x'),
                        'youtube' => school_setting('website_youtube'),
                    ]
                ),
                'siteMapSrc' => $this->mapEmbedSrc(school_setting('website_map_embed')),
                'homeSections' => \App\Services\HomeSections::enabled(school_setting('website_home_sections')),
                'headerMenu' => $this->publicMenu('header'),
                'footerMenu' => $this->publicMenu('footer'),
                'loginMenu' => $this->publicMenu('login'),
                'featuredMenu' => $this->publicMenu('featured'),
                'footerInstitutionMenu' => $this->publicMenu('footer_institution'),
                'footerLinksMenu' => $this->publicMenu('footer_links'),
            ]);
        });

        Paginator::defaultView('vendor.pagination.app');
        Paginator::defaultSimpleView('vendor.pagination.app');

        RateLimiter::for('login', function (Request $request) {
            $identifier = (string) ($request->input('identifier') ?: $request->input('email'));

            return Limit::perMinute(5)->by(strtolower(trim($identifier)).'|'.$request->ip());
        });
    }

    private function publicMenu(string $location): ?WebsiteMenu
    {
        try {
            return WebsiteMenu::forLocation($location);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Accept either a plain Google Maps embed URL or the full <iframe> snippet
     * copied from Google Maps, and return the src to render.
     */
    private function mapEmbedSrc(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $value, $matches)) {
            return $matches[1];
        }

        return preg_match('#^https?://#i', $value) ? $value : null;
    }
}
