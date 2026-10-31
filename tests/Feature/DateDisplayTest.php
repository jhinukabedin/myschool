<?php

namespace Tests\Feature;

use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\DateCalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class DateDisplayTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_renders_all_six_calendar_and_language_modes(): void
    {
        $this->assertFormatted('bangla', 'bangla', 'বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ');
        $this->assertFormatted('english', 'bangla', 'বুধবার, ২ সেপ্টেম্বর ২০২৬');
        $this->assertFormatted('both', 'bangla', 'বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ | ২ সেপ্টেম্বর ২০২৬');
        $this->assertFormatted('bangla', 'english', 'Wednesday, 18 Bhadro 1433 Bangabda');
        $this->assertFormatted('english', 'english', 'Wednesday, 2 September 2026');
        $this->assertFormatted('both', 'english', 'Wednesday, 18 Bhadro 1433 Bangabda | 2 September 2026');
    }

    public function test_weekday_and_year_toggles(): void
    {
        $this->assertFormatted('bangla', 'bangla', '১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ', ['date_show_weekday' => '0']);
        $this->assertFormatted('bangla', 'bangla', 'বুধবার, ১৮ ভাদ্র', ['date_show_bangla_year' => '0']);
        $this->assertFormatted('english', 'english', 'Wednesday, 2 September', ['date_show_english_year' => '0']);
        $this->assertFormatted('english', 'english', '2 September 2026', ['date_show_weekday' => '0']);
    }

    public function test_individual_calendars_can_be_hidden_in_both_mode(): void
    {
        $this->assertFormatted('both', 'bangla', 'বুধবার, ২ সেপ্টেম্বর ২০২৬', ['date_show_bangla' => '0']);
        $this->assertFormatted('both', 'bangla', 'বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ', ['date_show_english' => '0']);
        $this->assertFormatted('both', 'bangla', 'বুধবার', ['date_show_bangla' => '0', 'date_show_english' => '0']);
    }

    public function test_custom_separator_and_weekday_appears_once(): void
    {
        $formatted = $this->formatted('both', 'english', ['date_separator' => ' • ']);

        $this->assertSame('Wednesday, 18 Bhadro 1433 Bangabda • 2 September 2026', $formatted);
        $this->assertSame(1, substr_count($formatted, 'Wednesday'));
    }

    public function test_it_returns_null_when_the_display_is_disabled(): void
    {
        $this->configure([
            'enable_date_display' => '0',
            'date_calendar' => 'both',
        ]);

        $this->assertNull(app(DateCalendarService::class)->format($this->moment()));
        $this->assertSame('', trim(Blade::render('<x-date-display />')));
    }

    public function test_it_uses_the_application_timezone(): void
    {
        $this->configure(['date_calendar' => 'bangla', 'date_language' => 'bangla']);

        // 20:30 UTC on 1 September is already 2 September in Dhaka.
        $moment = CarbonImmutable::parse('2026-09-01 20:30:00', 'UTC');

        config(['app.timezone' => 'Asia/Dhaka']);
        $this->assertSame('বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ', app(DateCalendarService::class)->format($moment));

        config(['app.timezone' => 'UTC']);
        $this->assertSame('মঙ্গলবার, ১৭ ভাদ্র ১৪৩৩ বঙ্গাব্দ', app(DateCalendarService::class)->format($moment));
    }

    public function test_the_component_renders_a_specific_date_and_escapes_output(): void
    {
        $this->configure(['date_calendar' => 'both', 'date_language' => 'english']);

        $html = Blade::render('<x-date-display class="date-pill" :date="$date" :icon="true" />', [
            'date' => $this->moment(),
        ]);

        $this->assertStringContainsString('date-pill', $html);
        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringContainsString('title="Wednesday, 18 Bhadro 1433 Bangabda | 2 September 2026"', $html);
        $this->assertStringContainsString('Wednesday, 18 Bhadro 1433 Bangabda | 2 September 2026', $html);
    }

    public function test_the_dashboard_topbar_displays_the_configured_date(): void
    {
        $this->seedCore();
        $this->configure(['date_calendar' => 'both', 'date_language' => 'bangla']);
        CarbonImmutable::setTestNow($this->moment());

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('topbar-date', false)
            ->assertSee('বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ')
            ->assertSee('২ সেপ্টেম্বর ২০২৬');
    }

    public function test_the_dashboard_topbar_hides_the_date_when_disabled(): void
    {
        $this->seedCore();
        $this->configure(['enable_date_display' => '0']);
        CarbonImmutable::setTestNow($this->moment());

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('topbar-date', false);
    }

    public function test_the_settings_page_shows_the_date_and_calendar_section(): void
    {
        $this->seedCore();

        $this->actingAs($this->admin())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Date & Calendar Settings')
            ->assertSee('enable_date_display')
            ->assertSee('date_calendar')
            ->assertSee('date_language')
            ->assertSee('date_show_weekday')
            ->assertSee('date_separator');
    }

    public function test_the_date_settings_are_saved_through_the_existing_settings_form(): void
    {
        $this->seedCore();

        $this->actingAs($this->admin())
            ->put(route('settings.update'), [
                'school_name' => 'Test School',
                'currency_symbol' => '৳',
                'currency_code' => 'BDT',
                'locale' => 'en',
                'timezone' => 'Asia/Dhaka',
                'due_reminder_days' => 7,
                'fee_allocation_order' => 'oldest',
                'whatsapp_provider' => 'none',
                'enable_date_display' => '1',
                'date_calendar' => 'both',
                'date_language' => 'english',
                'date_show_weekday' => '0',
                'date_show_bangla' => '1',
                'date_show_bangla_year' => '0',
                'date_show_english' => '1',
                'date_show_english_year' => '0',
                'date_separator' => ' / ',
            ])
            ->assertRedirect();

        $this->assertSame('both', SchoolSetting::get('date_calendar'));
        $this->assertSame('english', SchoolSetting::get('date_language'));
        $this->assertSame('0', SchoolSetting::get('date_show_weekday'));
        $this->assertSame('1', SchoolSetting::get('date_show_bangla'));
        $this->assertSame('0', SchoolSetting::get('date_show_bangla_year'));
        $this->assertSame('1', SchoolSetting::get('date_show_english'));
        $this->assertSame('0', SchoolSetting::get('date_show_english_year'));
        $this->assertSame(' / ', SchoolSetting::get('date_separator'));

        $this->assertSame(
            '18 Bhadro / 2 September',
            app(DateCalendarService::class)->format($this->moment())
        );
    }

    public function test_missing_or_invalid_settings_fall_back_to_the_documented_defaults(): void
    {
        // A fresh install (or an older release) has no date_* rows at all.
        SchoolSetting::query()->where('key', 'like', 'date\_%')->delete();
        SchoolSetting::flush();

        $this->assertSame(
            'বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ',
            app(DateCalendarService::class)->format($this->moment())
        );

        $this->configure(['date_calendar' => 'nonsense', 'date_language' => 'fr']);

        $this->assertSame(
            'বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ',
            app(DateCalendarService::class)->format($this->moment())
        );
    }

    public function test_the_date_is_recomputed_after_midnight_but_memoised_within_the_day(): void
    {
        $this->configure(['date_calendar' => 'bangla', 'date_language' => 'bangla']);

        $service = app(DateCalendarService::class);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 23:59:00', 'Asia/Dhaka'));
        $beforeMidnight = $service->format();
        $this->assertSame('বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ', $beforeMidnight);

        // A second call on the same day reuses the memoised value.
        $this->assertSame($beforeMidnight, $service->format());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 00:01:00', 'Asia/Dhaka'));
        $this->assertSame('বৃহস্পতিবার, ১৯ ভাদ্র ১৪৩৩ বঙ্গাব্দ', $service->format());
    }

    public function test_the_public_website_utility_bar_displays_the_date(): void
    {
        $this->seedCore();
        $this->configure(['date_calendar' => 'both', 'date_language' => 'bangla']);
        SchoolSetting::put('website_show_topbar', '1', 'website');
        CarbonImmutable::setTestNow($this->moment());

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('topbar-lead', false)
            ->assertSee('topbar-date', false)
            ->assertSee('topbar-login', false)
            ->assertSee('বুধবার, ১৮ ভাদ্র ১৪৩৩ বঙ্গাব্দ')
            ->assertSee('২ সেপ্টেম্বর ২০২৬');
    }

    public function test_the_public_utility_bar_respects_the_show_and_date_toggles(): void
    {
        $this->seedCore();
        $this->configure(['date_calendar' => 'bangla', 'date_language' => 'bangla']);
        CarbonImmutable::setTestNow($this->moment());

        SchoolSetting::put('website_show_topbar', '0', 'website');

        $this->get(route('website.home'))
            ->assertOk()
            ->assertDontSee('topbar-date', false);

        SchoolSetting::put('website_show_topbar', '1', 'website');
        SchoolSetting::put('enable_date_display', '0', 'date');

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('topbar-lead', false)
            ->assertDontSee('topbar-date', false);
    }

    public function test_the_website_design_page_exposes_utility_bar_text_and_visibility_controls(): void
    {
        $this->seedCore();

        $this->actingAs($this->admin())
            ->get(route('website-admin.design'))
            ->assertOk()
            ->assertSee('Utility bar')
            ->assertSee('Utility bar text')
            ->assertSee('website_show_topbar', false);
    }

    private function assertFormatted(string $calendar, string $language, string $expected, array $overrides = []): void
    {
        $this->assertSame($expected, $this->formatted($calendar, $language, $overrides));
    }

    private function formatted(string $calendar, string $language, array $overrides = []): ?string
    {
        $this->configure(array_merge([
            'date_calendar' => $calendar,
            'date_language' => $language,
        ], $overrides));

        return app(DateCalendarService::class)->format($this->moment());
    }

    private function configure(array $overrides = []): void
    {
        $settings = array_merge([
            'enable_date_display' => '1',
            'date_calendar' => 'bangla',
            'date_language' => 'bangla',
            'date_show_weekday' => '1',
            'date_show_bangla' => '1',
            'date_show_bangla_year' => '1',
            'date_show_english' => '1',
            'date_show_english_year' => '1',
            'date_separator' => ' | ',
        ], $overrides);

        foreach ($settings as $key => $value) {
            SchoolSetting::put($key, $value, 'date');
        }
    }

    private function moment(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-09-02 10:00:00', 'Asia/Dhaka');
    }

    private function admin(): User
    {
        return User::query()
            ->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))
            ->firstOrFail();
    }
}
