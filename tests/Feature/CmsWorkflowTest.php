<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\WebsiteContent;
use App\Models\WebsiteMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class CmsWorkflowTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_rich_content_is_sanitized_revised_and_restored(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $body = '<p>Hello <b>world</b><script>alert(1)</script></p>'
            .'<p onclick="steal()">Unsafe <a href="javascript:alert(1)">link</a></p>'
            .'<img src="javascript:alert(1)" alt="bad">';

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'page',
            'title' => 'Safeguarding Policy',
            'body' => $body,
            'body_format' => 'html',
            'status' => 'draft',
            'template' => 'default',
        ])->assertRedirect(route('website-content.index'));

        $content = WebsiteContent::query()->where('title', 'Safeguarding Policy')->firstOrFail();

        $this->assertSame('html', $content->body_format);
        $this->assertStringNotContainsString('<script', $content->body);
        $this->assertStringNotContainsString('javascript:', $content->body);
        $this->assertStringNotContainsString('onclick', $content->body);
        $this->assertStringContainsString('<b>world</b>', $content->body);

        $this->get(route('website.page', $content->slug))->assertNotFound();

        $this->actingAs($admin)->put(route('website-content.update', $content), [
            'type' => 'page',
            'title' => 'Safeguarding Policy',
            'body' => '<p>Updated <i>copy</i> for families.</p>',
            'body_format' => 'html',
            'status' => 'published',
            'template' => 'sidebar',
            'meta_title' => 'Safeguarding',
            'meta_description' => 'Our safeguarding policy.',
        ])->assertRedirect(route('website-content.edit', $content));

        $content->refresh();
        $this->assertSame('published', $content->status);
        $this->assertNotNull($content->published_at);
        $this->assertDatabaseCount('website_content_revisions', 1);

        $revision = $content->revisions()->firstOrFail();
        $this->assertStringContainsString('<b>world</b>', $revision->payload['body']);
        $this->assertStringContainsString('Updated', $content->body);

        $this->get(route('website.page', $content->slug))
            ->assertOk()
            ->assertSee('Updated')
            ->assertSee('<i>copy</i>', false);

        $this->actingAs($admin)->post(route('website-content.revisions.restore', [$content, $revision]))
            ->assertRedirect(route('website-content.edit', $content));

        $content->refresh();
        $this->assertSame($revision->payload['body'], $content->body);
        $this->assertSame('draft', $content->status);
        $this->assertSame(2, $content->revisions()->count());

        // Legacy plain text remains escaped rather than being rendered as HTML.
        $legacy = WebsiteContent::create([
            'type' => 'page',
            'title' => 'Legacy Notice',
            'slug' => 'legacy-notice',
            'body' => "<b>old</b>\nsecond line",
            'body_format' => 'text',
            'status' => 'published',
            'published_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->assertStringContainsString('&lt;b&gt;old&lt;/b&gt;', $legacy->body_html);
        $this->get(route('website.page', 'legacy-notice'))->assertSee('&lt;b&gt;old&lt;/b&gt;', false);
    }

    public function test_media_library_uploads_updates_and_deletes_files_safely(): void
    {
        $this->seedCore();
        $admin = $this->admin();
        Storage::fake('public');

        $this->actingAs($admin)->post(route('media.store'), [
            'files' => [UploadedFile::fake()->create('prospectus.pdf', 128, 'application/pdf')],
            'category' => 'Documents',
            'alt_text' => 'School prospectus',
        ])->assertRedirect(route('media.index'));

        $media = Media::query()->firstOrFail();
        $this->assertSame('prospectus.pdf', $media->original_name);
        $this->assertSame('Documents', $media->category);
        Storage::disk('public')->assertExists($media->path);

        $this->actingAs($admin)->put(route('media.update', $media), [
            'alt_text' => 'Updated prospectus',
            'caption' => 'Admissions 2026',
            'category' => 'Admissions',
        ])->assertRedirect();

        $this->assertSame('Updated prospectus', $media->fresh()->alt_text);

        // A file referenced by a page cannot be deleted from the library.
        WebsiteContent::create([
            'type' => 'page',
            'title' => 'Uses the prospectus',
            'slug' => 'uses-prospectus',
            'file_path' => $media->path,
            'status' => 'draft',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('media.destroy', $media))->assertSessionHas('error');
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);

        WebsiteContent::query()->where('file_path', $media->path)->delete();

        $this->actingAs($admin)->delete(route('media.destroy', $media))->assertSessionHas('success');
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_public_menus_and_gallery_render_managed_content(): void
    {
        $this->seedCore();
        $admin = $this->admin();
        Storage::fake('public');

        $menu = WebsiteMenu::query()->where('location', 'header')->firstOrFail();
        $menu->update(['is_active' => true]);
        $item = $menu->items()->create([
            'title' => 'Managed Admissions Link',
            'route_name' => 'website.admission',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $this->get(route('website.home'))->assertOk()->assertSee('Managed Admissions Link');

        $menu->update(['is_active' => false]);
        $this->get(route('website.home'))->assertOk()->assertDontSee('Managed Admissions Link');

        $gallery = WebsiteContent::create([
            'type' => 'gallery',
            'title' => 'Science Fair Photo',
            'slug' => 'science-fair-photo',
            'excerpt' => 'Students presenting their projects.',
            'image_path' => 'website/media/science-fair.jpg',
            'status' => 'published',
            'published_at' => now(),
            'sort_order' => 1,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->get(route('website.gallery'))
            ->assertOk()
            ->assertSee('Science Fair Photo')
            ->assertSee('data-gallery-image', false)
            ->assertSee('gallery-lightbox', false);

        $this->actingAs($admin)->delete(route('website-content.destroy', $gallery))
            ->assertRedirect(route('website-content.index'));

        $this->assertDatabaseMissing('website_contents', ['id' => $gallery->id]);
        $this->get(route('website.gallery'))->assertDontSee('Science Fair Photo');
    }

    public function test_rich_text_keeps_safe_image_sizing_and_strips_unsafe_css(): void
    {
        $this->seedCore();
        $admin = $this->admin();

        $body = '<p style="text-align: center">Centered intro</p>'
            .'<img src="/storage/website/images/photo.jpg" alt="Photo" style="width: 320px; height: auto">'
            .'<img src="/storage/website/images/evil.jpg" alt="Evil" style="position: fixed; background: url(javascript:alert(1)); width: 100px">'
            .'<p style="color: red; position: absolute; background-image: url(https://evil.test/x.png)">Styled text</p>';

        $this->actingAs($admin)->post(route('website-content.store'), [
            'type' => 'page',
            'title' => 'Resized Content',
            'body' => $body,
            'body_format' => 'html',
            'status' => 'published',
        ])->assertRedirect(route('website-content.index'));

        $content = WebsiteContent::query()->where('title', 'Resized Content')->firstOrFail();

        // Safe layout declarations survive so image resizing and alignment stick.
        $this->assertStringContainsString('text-align: center', $content->body);
        $this->assertStringContainsString('width: 320px', $content->body);
        $this->assertStringContainsString('height: auto', $content->body);
        $this->assertStringContainsString('color: red', $content->body);

        // Dangerous or unsupported declarations are removed.
        $this->assertStringNotContainsString('position', $content->body);
        $this->assertStringNotContainsString('javascript:', $content->body);
        $this->assertStringNotContainsString('url(', $content->body);
        $this->assertStringNotContainsString('background-image', $content->body);

        $this->get(route('website.page', $content->slug))
            ->assertOk()
            ->assertSee('width: 320px', false)
            ->assertSee('text-align: center', false)
            ->assertDontSee('javascript:', false);
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }
}
