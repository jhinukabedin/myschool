<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Models\WebsiteContent;
use App\Models\WebsiteMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class WebsiteSectionsTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_header_settings_save_and_control_the_public_header(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('website-admin.header'))
            ->assertOk()
            ->assertSee('Header Area');

        $this->actingAs($admin)->put(route('website-admin.header.update'), [
            'website_show_date' => '0',
            'website_header_date_position' => 'right',
            'website_show_login_menu' => '1',
            'website_header_login_label' => 'Portal Login',
            'website_header_login_border_radius' => '14',
        ])->assertRedirect();

        $this->assertSame('0', SchoolSetting::get('website_show_date'));
        $this->assertSame('right', SchoolSetting::get('website_header_date_position'));
        $this->assertSame('14', SchoolSetting::get('website_header_login_border_radius'));

        $this->get(route('website.home'))
            ->assertOk()
            ->assertDontSee('topbar-date', false);
    }

    public function test_login_menu_items_render_as_bordered_buttons_and_respect_enabled_state(): void
    {
        $this->seedCore();

        $menu = WebsiteMenu::create(['name' => 'Login menu', 'location' => 'login', 'is_active' => true]);
        $menu->items()->create(['title' => 'Student Portal', 'url' => '/student/login', 'is_active' => true, 'target' => '_self', 'sort_order' => 0]);
        $menu->items()->create(['title' => 'Hidden Portal', 'url' => '/hidden', 'is_active' => false, 'target' => '_self', 'sort_order' => 1]);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('Student Portal')
            ->assertDontSee('Hidden Portal')
            ->assertSee('border-radius:10px', false);
    }

    public function test_footer_settings_and_menus_control_the_public_footer(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('website-admin.footer.update'), [
            'website_footer_about' => 'Dynamic footer about text',
            'website_footer_institution_title' => 'About the school',
            'website_footer_links_title' => 'Useful links',
            'website_footer_contact_address' => '123 Dynamic Road',
            'website_footer_contact_email' => 'dynamic@school.test',
            'website_footer_contact_phone' => '+880 1999 000000',
            'website_facebook' => '',
            'website_x' => '',
            'website_youtube' => '',
        ])->assertRedirect();

        $institution = WebsiteMenu::create(['name' => 'Footer — Institution', 'location' => 'footer_institution', 'is_active' => true]);
        $institution->items()->create(['title' => 'Our History', 'url' => '/about', 'is_active' => true, 'target' => '_self', 'sort_order' => 0]);

        $links = WebsiteMenu::create(['name' => 'Footer — Important links', 'location' => 'footer_links', 'is_active' => true]);
        $links->items()->create(['title' => 'Exam Routine', 'url' => '/downloads', 'is_active' => true, 'target' => '_self', 'sort_order' => 0]);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('Dynamic footer about text')
            ->assertSee('About the school')
            ->assertSee('Useful links')
            ->assertSee('Our History')
            ->assertSee('Exam Routine')
            ->assertSee('123 Dynamic Road')
            ->assertSee('dynamic@school.test')
            ->assertSee('+880 1999 000000');
    }

    public function test_hero_settings_and_featured_menu_control_the_homepage(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('website-admin.hero.update'), [
            'website_hero_tagline' => 'Dynamic tagline',
            'website_hero_title' => 'Dynamic hero title',
            'website_hero_description' => 'Dynamic hero description',
            'website_hero_menu_name' => 'Apply online',
            'website_hero_menu_link' => '/admission/apply',
        ])->assertRedirect();

        $featured = WebsiteMenu::create(['name' => 'Hero featured menu', 'location' => 'featured', 'is_active' => true]);
        $featured->items()->create(['title' => 'Featured Results', 'url' => '/results', 'is_active' => true, 'target' => '_self', 'sort_order' => 0]);
        $featured->items()->create(['title' => 'Disabled Feature', 'url' => '/disabled', 'is_active' => false, 'target' => '_self', 'sort_order' => 1]);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('Dynamic tagline')
            ->assertSee('Dynamic hero title')
            ->assertSee('Dynamic hero description')
            ->assertSee('Apply online')
            ->assertSee('Featured Results')
            ->assertDontSee('Disabled Feature');
    }

    public function test_sliders_are_managed_and_disabled_slides_stay_hidden(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $live = WebsiteContent::create([
            'type' => 'slider',
            'title' => 'Live slide title',
            'slug' => 'live-slide',
            'excerpt' => 'Live slide tagline',
            'body' => 'Live slide description',
            'button_label' => 'Live button',
            'link_url' => '/admission',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $draft = WebsiteContent::create([
            'type' => 'slider',
            'title' => 'Draft slide title',
            'slug' => 'draft-slide',
            'body' => 'Draft slide description',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->get(route('website-admin.sliders'))
            ->assertOk()
            ->assertSee('Live slide title')
            ->assertSee('Draft slide title');

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('Live slide title')
            ->assertDontSee('Draft slide title');

        $this->actingAs($admin)->post(route('website-content.toggle', $live))->assertRedirect();
        $this->assertSame('draft', $live->fresh()->status);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertDontSee('Live slide title');
    }

    public function test_featured_menu_items_render_icon_and_subtitle(): void
    {
        $this->seedCore();

        $featured = WebsiteMenu::create(['name' => 'Hero featured menu', 'location' => 'featured', 'is_active' => true]);
        $featured->items()->create([
            'title' => 'ক্লাস রুটিন',
            'subtitle' => 'শ্রেণিভিত্তিক সময়সূচি',
            'icon' => 'calendar',
            'url' => '/class-routine',
            'is_active' => true,
            'target' => '_self',
            'sort_order' => 0,
        ]);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('ক্লাস রুটিন')
            ->assertSee('শ্রেণিভিত্তিক সময়সূচি')
            ->assertSee('class="icon"', false);
    }

    public function test_featured_menu_is_limited_to_six_items(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $featured = WebsiteMenu::create(['name' => 'Hero featured menu', 'location' => 'featured', 'is_active' => true]);

        for ($i = 1; $i <= 6; $i++) {
            $featured->items()->create([
                'title' => "Featured {$i}",
                'url' => "/featured-{$i}",
                'is_active' => true,
                'target' => '_self',
                'sort_order' => $i,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('website-menus.items.store', $featured), [
                'title' => 'Featured seven',
                'url' => '/featured-7',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('title');

        $this->assertSame(6, $featured->items()->count());

        // Other menu locations keep unlimited items.
        $header = WebsiteMenu::create(['name' => 'Main menu', 'location' => 'header', 'is_active' => true]);
        for ($i = 1; $i <= 6; $i++) {
            $header->items()->create([
                'title' => "Header {$i}",
                'url' => "/header-{$i}",
                'is_active' => true,
                'target' => '_self',
                'sort_order' => $i,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('website-menus.items.store', $header), [
                'title' => 'Header seven',
                'url' => '/header-7',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(7, $header->items()->count());
    }

    public function test_slider_can_be_saved_without_a_title(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'slider',
            'title' => '',
            'status' => 'published',
            'sort_order' => 1,
        ])->assertRedirect(route('website-content.index'));

        $slide = WebsiteContent::query()->ofType('slider')->firstOrFail();

        $this->assertSame('', $slide->title);
        $this->assertSame('published', $slide->status);
        $this->assertNotNull($slide->slug);

        $this->get(route('website.home'))->assertOk();
    }

    public function test_footer_social_links_repeater_saves_and_renders_brand_coloured_icons(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('website-admin.footer.update'), [
            'website_footer_institution_title' => 'Institute',
            'website_footer_links_title' => 'Links',
            'social_links' => [
                ['network' => 'facebook', 'url' => 'https://facebook.com/school'],
                ['network' => 'youtube', 'url' => 'https://youtube.com/@school'],
                ['network' => 'whatsapp', 'url' => 'https://wa.me/8801700000000'],
                ['network' => 'bogus', 'url' => 'https://bogus.example.com'],
                ['network' => 'x', 'url' => 'javascript:alert(1)'],
            ],
        ])->assertRedirect();

        $stored = json_decode((string) SchoolSetting::get('website_social_links'), true);
        $this->assertCount(3, $stored);
        $this->assertSame(['facebook', 'youtube', 'whatsapp'], array_column($stored, 'network'));

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('footer-social', false)
            ->assertSee('background:#1877F2', false)
            ->assertSee('background:#FF0000', false)
            ->assertSee('background:#25D366', false)
            ->assertDontSee('javascript:alert(1)', false);
    }

    public function test_footer_social_links_fall_back_to_legacy_settings(): void
    {
        $this->seedCore();

        SchoolSetting::put('website_facebook', 'https://facebook.com/legacy', 'website');

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('https://facebook.com/legacy', false)
            ->assertSee('background:#1877F2', false);
    }

    public function test_clearing_the_social_links_repeater_removes_even_legacy_icons(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        SchoolSetting::put('website_facebook', 'https://facebook.com/legacy', 'website');

        // Saving the footer without any repeater rows clears the list.
        $this->actingAs($admin)->put(route('website-admin.footer.update'), [
            'website_footer_institution_title' => 'Institute',
            'website_footer_links_title' => 'Links',
        ])->assertRedirect();

        $this->get(route('website.home'))
            ->assertOk()
            ->assertDontSee('https://facebook.com/legacy', false)
            ->assertDontSee('footer-social', false);
    }

    public function test_home_sections_toggle_hides_and_shows_blocks(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        // Only the introduction block stays visible.
        $this->actingAs($admin)->put(route('website-admin.home-sections.update'), [
            'sections' => ['introduction'],
        ])->assertRedirect();

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('প্রতিষ্ঠানের পরিচিতি')
            ->assertDontSee('প্রশাসনের বাণী')
            ->assertDontSee('একাডেমিক কর্নার');

        // Selecting every block again restores them.
        $this->actingAs($admin)->put(route('website-admin.home-sections.update'), [
            'sections' => \App\Services\HomeSections::all(),
        ])->assertRedirect();

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('প্রশাসনের বাণী')
            ->assertSee('একাডেমিক কর্নার')
            ->assertSee('এক নজরে');
    }

    public function test_google_map_embed_accepts_iframe_snippet_and_plain_url(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('website-admin.footer.update'), [
            'website_footer_institution_title' => 'Institute',
            'website_footer_links_title' => 'Links',
            'website_map_embed' => '<iframe src="https://www.google.com/maps/embed?pb=example" width="600" height="450"></iframe>',
        ])->assertRedirect();

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('www.google.com/maps/embed?pb=example', false);

        $this->actingAs($admin)->put(route('website-admin.footer.update'), [
            'website_footer_institution_title' => 'Institute',
            'website_footer_links_title' => 'Links',
            'website_map_embed' => 'https://maps.google.com/maps?q=dhaka&output=embed',
        ])->assertRedirect();

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('maps.google.com/maps?q=dhaka', false);
    }

    public function test_notice_overview_lists_notices_and_links_to_the_manager(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        Notice::create([
            'title' => 'Website Notice Overview',
            'body' => 'Body text',
            'audience' => 'all',
            'status' => 'published',
            'publish_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)->get(route('website-admin.notice'))
            ->assertOk()
            ->assertSee('Website Notice Overview')
            ->assertSee(route('notices.index'), false);
    }

    public function test_new_content_managers_create_and_publish_items(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $types = [
            'admission_info' => 'Admission Info Manager Item',
            'routine' => 'Class Routine Manager Item',
            'academic_calendar' => 'Calendar Manager Item',
            'syllabus' => 'Syllabus Manager Item',
            'video' => 'Video Manager Item',
        ];

        foreach ($types as $type => $title) {
            $this->actingAs($admin)->post(route('website-content.store'), [
                'type' => $type,
                'title' => $title,
                'status' => 'published',
            ])->assertRedirect(route('website-content.index'));

            $this->assertDatabaseHas('website_contents', ['title' => $title, 'type' => $type]);
        }

        $this->get(route('website.class-routine'))->assertOk()->assertSee('Class Routine Manager Item');
        $this->get(route('website.video-gallery'))->assertOk()->assertSee('Video Manager Item');
        $this->get(route('website.downloads'))->assertOk()->assertSee('Syllabus Manager Item');
        $this->get(route('website.calendar'))->assertOk()->assertSee('Calendar Manager Item');
        $this->get(route('website.admission'))->assertOk()->assertSee('Admission Info Manager Item');
    }

    public function test_rich_text_editor_image_upload_returns_a_public_url(): void
    {
        Storage::fake('public');
        $this->seedCore();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('website-content.image'), [
            'image' => UploadedFile::fake()->image('inline.jpg', 640, 480),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $url = $response->json('url');
        $this->assertStringContainsString('/storage/website/images/', $url);

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', ['original_name' => 'inline.jpg', 'disk' => 'public']);
    }

    public function test_introduction_shows_short_fields_on_home_and_body_on_details_page(): void
    {
        $this->seedCore();

        WebsiteContent::create([
            'type' => 'introduction',
            'title' => 'প্রতিষ্ঠানের পরিচিতি',
            'slug' => 'prtishthaner-priciti',
            'subtitle' => 'আলোকিত প্রজন্ম গড়ার অঙ্গীকার',
            'excerpt' => 'সংক্ষিপ্ত বর্ণনা এখানে।',
            'image_title' => 'আমাদের বিদ্যালয়',
            'body' => '<h2>বিস্তারিত বর্ণনা</h2><p>শুধু ডিটেইলস পেজে দেখা যাবে।</p>',
            'body_format' => 'html',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('প্রতিষ্ঠানের পরিচিতি')
            ->assertSee('আলোকিত প্রজন্ম গড়ার অঙ্গীকার')
            ->assertSee('সংক্ষিপ্ত বর্ণনা এখানে।')
            ->assertSee('আমাদের বিদ্যালয়')
            ->assertDontSee('শুধু ডিটেইলস পেজে দেখা যাবে');

        $this->get(route('website.about'))
            ->assertOk()
            ->assertSee('প্রতিষ্ঠানের পরিচিতি')
            ->assertSee('আলোকিত প্রজন্ম গড়ার অঙ্গীকার')
            ->assertSee('শুধু ডিটেইলস পেজে দেখা যাবে')
            ->assertDontSee('সংক্ষিপ্ত বর্ণনা এখানে।');
    }

    public function test_introduction_admin_form_saves_home_and_details_fields(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'introduction',
            'title' => 'প্রতিষ্ঠানের পরিচিতি',
            'subtitle' => 'উপশিরোনাম',
            'excerpt' => 'সংক্ষিপ্ত বর্ণনা',
            'image_title' => 'বিদ্যালয়ের নাম',
            'body' => '<p>বিস্তারিত বর্ণনা</p>',
            'body_format' => 'html',
            'status' => 'published',
        ])->assertRedirect(route('website-content.index'));

        $this->assertDatabaseHas('website_contents', [
            'type' => 'introduction',
            'subtitle' => 'উপশিরোনাম',
            'image_title' => 'বিদ্যালয়ের নাম',
            'excerpt' => 'সংক্ষিপ্ত বর্ণনা',
        ]);
    }

    public function test_rich_text_editor_is_only_loaded_on_forms_with_a_body_field(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        // Body-based content gets the full editor plus the split HTML pane.
        $this->actingAs($admin)
            ->get(route('website-content.create', ['type' => 'page']))
            ->assertOk()
            ->assertSee('vendor/jodit/jodit.min.css', false)
            ->assertSee('vendor/jodit/jodit.min.js', false)
            ->assertSee('id="rich-body"', false)
            ->assertSee('id="rich-code-pane"', false)
            ->assertSee('filesVariableName', false);

        // Forms without a body stay lean: no editor assets, no markup.
        $this->actingAs($admin)
            ->get(route('website-content.create', ['type' => 'slider']))
            ->assertOk()
            ->assertDontSee('vendor/jodit/jodit.min.js', false)
            ->assertDontSee('id="rich-body"', false)
            ->assertDontSee('id="rich-code-pane"', false);
    }

    public function test_menu_items_can_be_reordered_up_and_down(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $featured = WebsiteMenu::create(['name' => 'Hero featured menu', 'location' => 'featured', 'is_active' => true]);

        $first = $featured->items()->create(['title' => 'First', 'url' => '/first', 'is_active' => true, 'target' => '_self', 'sort_order' => 1]);
        $second = $featured->items()->create(['title' => 'Second', 'url' => '/second', 'is_active' => true, 'target' => '_self', 'sort_order' => 2]);
        $third = $featured->items()->create(['title' => 'Third', 'url' => '/third', 'is_active' => true, 'target' => '_self', 'sort_order' => 3]);

        // Move the last item up one position.
        $this->actingAs($admin)
            ->post(route('website-menus.items.move', $third), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(
            ['First', 'Third', 'Second'],
            $featured->items()->pluck('title')->all()
        );
        $this->assertSame([1, 2, 3], $featured->items()->pluck('sort_order')->all());

        // Move the now-first item down again.
        $this->actingAs($admin)
            ->post(route('website-menus.items.move', $first), ['direction' => 'down'])
            ->assertRedirect();

        $this->assertSame(
            ['Third', 'First', 'Second'],
            $featured->items()->pluck('title')->all()
        );

        // Out-of-range moves are a no-op.
        $this->actingAs($admin)
            ->post(route('website-menus.items.move', $second), ['direction' => 'down'])
            ->assertRedirect();

        $this->assertSame(['Third', 'First', 'Second'], $featured->items()->pluck('title')->all());
    }

    public function test_featured_menu_button_can_be_edited_including_order(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $featured = WebsiteMenu::create(['name' => 'Hero featured menu', 'location' => 'featured', 'is_active' => true]);
        $item = $featured->items()->create([
            'title' => 'Old title',
            'url' => '/old',
            'is_active' => true,
            'target' => '_self',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)->put(route('website-menus.items.update', $item), [
            'title' => 'New title',
            'subtitle' => 'New subtitle',
            'icon' => 'calendar',
            'url' => '/new',
            'target' => '_blank',
            'sort_order' => 4,
            'is_active' => 1,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame('New title', $item->title);
        $this->assertSame('New subtitle', $item->subtitle);
        $this->assertSame('calendar', $item->icon);
        $this->assertSame('/new', $item->url);
        $this->assertSame('_blank', $item->target);
        $this->assertSame(4, $item->sort_order);
        $this->assertTrue($item->is_active);

        // The featured area renders the edited values on the homepage.
        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('New title')
            ->assertSee('New subtitle');
    }

    public function test_administration_messages_are_repeatable_and_layout_follows_count(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $payload = fn (string $title): array => [
            'type' => 'administration_message',
            'title' => $title,
            'excerpt' => $title.' সংক্ষিপ্ত বর্ণনা',
            'body' => '<p>'.$title.' এর বিস্তারিত বাণী</p>',
            'body_format' => 'html',
            'status' => 'published',
        ];

        // The first message must create a new record instead of opening an
        // existing one for editing.
        $this->actingAs($admin)->post(route('website-content.store'), $payload('প্রথম বাণী'))
            ->assertRedirect(route('website-content.index'));

        // One message fills the whole row and has no slider controls.
        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('প্রথম বাণী')
            ->assertSee('--message-visible: 1', false)
            ->assertDontSee('message-prev', false);

        $this->actingAs($admin)->post(route('website-content.store'), $payload('দ্বিতীয় বাণী'))
            ->assertRedirect(route('website-content.index'));

        // Two messages split into two columns.
        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('প্রথম বাণী')
            ->assertSee('দ্বিতীয় বাণী')
            ->assertSee('--message-visible: 2', false);

        $this->actingAs($admin)->post(route('website-content.store'), $payload('তৃতীয় বাণী'))
            ->assertRedirect(route('website-content.index'));

        // Three or more messages turn the row into a slider.
        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('তৃতীয় বাণী')
            ->assertSee('message-prev', false);

        $this->assertSame(3, WebsiteContent::query()->ofType('administration_message')->count());

        $first = WebsiteContent::query()->where('title', 'প্রথম বাণী')->firstOrFail();
        $this->get(route('website.administration-message.show', $first->slug))
            ->assertOk()
            ->assertSee('প্রথম বাণী এর বিস্তারিত বাণী');
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }
}