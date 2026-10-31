<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class NoticeBodyEditorTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_notice_form_shows_the_rich_editor_with_an_html_code_pane(): void
    {
        $this->seedCore();

        $this->actingAs($this->admin())
            ->get(route('notices.create'))
            ->assertOk()
            ->assertSee('vendor/jodit/jodit.min.css', false)
            ->assertSee('vendor/jodit/jodit.min.js', false)
            ->assertSee('id="rich-body"', false)
            ->assertSee('id="rich-code-pane"', false)
            ->assertSee('name="body_format"', false)
            ->assertSee('Show HTML code', false);
    }

    public function test_html_notice_bodies_are_sanitized_and_rendered_as_markup(): void
    {
        $this->seedCore();

        $this->actingAs($this->admin())->post(route('notices.store'), [
            'title' => 'Formatted notice',
            'body' => '<p>Hello <strong>world</strong></p><script>alert(1)</script><p onclick="alert(2)">Bad link</p>',
            'body_format' => 'html',
            'audience' => 'all',
            'status' => 'published',
            'publish_at' => now()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('notices.index'));

        $notice = Notice::query()->firstOrFail();

        $this->assertSame('html', $notice->body_format);
        $this->assertStringContainsString('<strong>world</strong>', $notice->body);
        $this->assertStringNotContainsString('<script', $notice->body);
        $this->assertStringNotContainsString('onclick', $notice->body);

        $this->get(route('website.notice', $notice))
            ->assertOk()
            ->assertSee('<strong>world</strong>', false)
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('alert(2)', false);
    }

    public function test_plain_text_notices_keep_their_line_breaks_and_stay_escaped(): void
    {
        $this->seedCore();

        $notice = Notice::create([
            'title' => 'Plain notice',
            'body' => "First line\nSecond line <b>not bold</b>",
            'audience' => 'all',
            'status' => 'published',
            'publish_at' => now(),
        ]);

        $this->assertSame('text', $notice->body_format);

        $this->get(route('website.notice', $notice))
            ->assertOk()
            ->assertSee('First line<br', false)
            ->assertSee('&lt;b&gt;not bold&lt;/b&gt;', false);
    }

    public function test_an_empty_editor_body_is_rejected(): void
    {
        $this->seedCore();

        $this->actingAs($this->admin())->post(route('notices.store'), [
            'title' => 'Empty notice',
            'body' => '<p><br></p>',
            'body_format' => 'html',
            'audience' => 'all',
            'status' => 'draft',
        ])->assertSessionHasErrors('body');

        $this->assertSame(0, Notice::query()->count());
    }

    public function test_notice_managers_can_upload_inline_images(): void
    {
        Storage::fake('public');
        $this->seedCore();

        $response = $this->actingAs($this->admin())->post(route('notices.image'), [
            'image' => UploadedFile::fake()->image('notice.jpg', 640, 480),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertStringContainsString('/storage/website/images/', $response->json('url'));

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', ['original_name' => 'notice.jpg', 'disk' => 'public']);
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }
}
