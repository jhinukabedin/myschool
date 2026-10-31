<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Examination;
use App\Models\Notice;
use App\Models\Role;
use App\Models\User;
use App\Models\WebsiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class WebsiteTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_public_pages_are_available_to_guests(): void
    {
        $this->seedCore();

        $notices = Notice::create([
            'title' => 'Public Notice',
            'body' => 'A public notice body.',
            'audience' => 'all',
            'status' => 'published',
            'publish_at' => now()->subHour(),
        ]);

        $news = WebsiteContent::create([
            'type' => 'news',
            'title' => 'Public News',
            'slug' => 'public-news',
            'body' => 'Public news body.',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $routes = [
            route('website.home'),
            route('website.about'),
            route('website.administration-message'),
            route('website.principal-message'),
            route('website.president-message'),
            route('website.academics'),
            route('website.faculty'),
            route('website.admission'),
            route('website.admission.apply'),
            route('website.notices'),
            route('website.notice', $notices),
            route('website.news'),
            route('website.news.item', $news->slug),
            route('website.events'),
            route('website.calendar'),
            route('website.gallery'),
            route('website.downloads'),
            route('website.class-routine'),
            route('website.video-gallery'),
            route('website.contact'),
            route('website.privacy'),
            route('website.sitemap'),
            route('website.robots'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_published_notices_are_public_but_drafts_and_staff_notices_are_hidden(): void
    {
        $this->seedCore();

        $published = Notice::create([
            'title' => 'Visible Admission Notice',
            'body' => 'Admission is open.',
            'audience' => 'all',
            'status' => 'published',
            'publish_at' => now()->subHour(),
        ]);

        $draft = Notice::create([
            'title' => 'Secret Draft Notice',
            'body' => 'Not ready.',
            'audience' => 'all',
            'status' => 'draft',
        ]);

        $staffOnly = Notice::create([
            'title' => 'Staff Only Notice',
            'body' => 'For staff.',
            'audience' => 'staff',
            'status' => 'published',
            'publish_at' => now()->subHour(),
        ]);

        $this->get(route('website.notices'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title)
            ->assertDontSee($staffOnly->title);

        $this->get(route('website.notice', $published))->assertOk()->assertSee($published->title);
        $this->get(route('website.notice', $draft))->assertNotFound();
        $this->get(route('website.notice', $staffOnly))->assertNotFound();
    }

    public function test_draft_news_is_hidden_and_published_news_is_visible(): void
    {
        $this->seedCore();

        WebsiteContent::create([
            'type' => 'news',
            'title' => 'Published Sports News',
            'slug' => 'published-sports-news',
            'body' => 'We won.',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        WebsiteContent::create([
            'type' => 'news',
            'title' => 'Unpublished Draft News',
            'slug' => 'unpublished-draft-news',
            'body' => 'Draft.',
            'status' => 'draft',
        ]);

        $this->get(route('website.news'))
            ->assertOk()
            ->assertSee('Published Sports News')
            ->assertDontSee('Unpublished Draft News');

        $this->get(route('website.news.item', 'published-sports-news'))->assertOk();
        $this->get(route('website.news.item', 'unpublished-draft-news'))->assertNotFound();
    }

    public function test_academic_calendar_hides_unpublished_exams(): void
    {
        $session = $this->seedCore();

        Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Public Calendar Exam',
            'exam_type' => 'terminal',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-05',
            'status' => 'published',
        ]);

        Examination::create([
            'academic_session_id' => $session->id,
            'name' => 'Internal Draft Exam',
            'exam_type' => 'terminal',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'status' => 'draft',
        ]);

        $this->get(route('website.calendar'))
            ->assertOk()
            ->assertSee('Public Calendar Exam')
            ->assertDontSee('Internal Draft Exam');
    }

    public function test_contact_form_validates_and_stores_messages_but_ignores_honeypot_spam(): void
    {
        $this->seedCore();

        $this->post(route('website.contact.store'), [])->assertSessionHasErrors(['name', 'message']);
        $this->assertDatabaseCount('contact_messages', 0);

        $this->post(route('website.contact.store'), [
            'name' => 'Guardian Name',
            'email' => 'guardian@example.com',
            'phone' => '+880 1700 123456',
            'subject' => 'Admission query',
            'message' => 'Please share the admission schedule.',
        ])->assertRedirect(route('website.contact'));

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Guardian Name',
            'email' => 'guardian@example.com',
            'status' => 'new',
        ]);

        // Honeypot submissions are acknowledged but never stored.
        $this->post(route('website.contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'spam@example.com',
            'message' => 'Buy something',
            'website_url' => 'http://spam.example.com',
        ])->assertRedirect(route('website.contact'));

        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_admission_application_creates_an_enquiry_for_staff_follow_up(): void
    {
        $this->seedCore();

        $this->post(route('website.admission.store'), [
            'student_name' => 'Website Applicant',
            'guardian_name' => 'Website Guardian',
            'phone' => '+880 1700 654321',
            'email' => 'applicant@example.com',
            'notes' => 'Please contact after 4pm.',
        ])->assertRedirect(route('website.admission.apply'));

        $this->assertDatabaseHas('admission_enquiries', [
            'student_name' => 'Website Applicant',
            'guardian_name' => 'Website Guardian',
            'phone' => '+880 1700 654321',
            'source' => 'Website',
            'status' => 'new',
            'created_by' => null,
        ]);

        $this->assertDatabaseCount('admission_enquiries', 1);
    }

    public function test_website_administration_requires_authentication_and_permission(): void
    {
        $this->seedCore();

        $this->get(route('website-content.index'))->assertRedirect(route('login'));
        $this->get(route('website-admin.index'))->assertRedirect(route('login'));
        $this->get(route('website-admin.design'))->assertRedirect(route('login'));
        $this->get(route('messages.index'))->assertRedirect(route('login'));

        $teacherRole = Role::query()->where('slug', 'teacher')->firstOrFail();
        $teacher = User::create([
            'role_id' => $teacherRole->id,
            'name' => 'Website Blocked Teacher',
            'email' => 'website.blocked.teacher@example.com',
            'password' => 'Secret@12345',
            'is_active' => true,
        ]);

        $this->actingAs($teacher)->get(route('website-content.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('website-admin.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('website-admin.design'))->assertForbidden();
        $this->actingAs($teacher)->get(route('messages.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('website-content.store'), [])->assertForbidden();
    }

    public function test_administrator_can_publish_content_and_read_contact_messages(): void
    {
        $this->seedCore();
        $admin = User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'news',
            'title' => 'CMS Published Article',
            'status' => 'published',
            'body' => 'Article body from the CMS.',
            'is_featured' => 0,
        ])->assertRedirect(route('website-content.index'));

        $content = WebsiteContent::query()->where('title', 'CMS Published Article')->firstOrFail();
        $this->assertSame('published', $content->status);
        $this->assertNotNull($content->published_at);
        $this->assertSame('cms-published-article', $content->slug);

        $this->get(route('website.news'))->assertOk()->assertSee('CMS Published Article');

        $message = ContactMessage::create([
            'name' => 'Inbox Tester',
            'email' => 'inbox@example.com',
            'message' => 'Hello office',
            'status' => 'new',
        ]);

        $this->actingAs($admin)->get(route('messages.index'))->assertOk()->assertSee('Inbox Tester');
        $this->actingAs($admin)->get(route('messages.show', $message))->assertOk()->assertSee('Hello office');

        $this->assertSame('read', $message->fresh()->status);

        $this->actingAs($admin)->post(route('messages.status', $message), ['status' => 'archived'])->assertRedirect();
        $this->assertSame('archived', $message->fresh()->status);
    }
}
