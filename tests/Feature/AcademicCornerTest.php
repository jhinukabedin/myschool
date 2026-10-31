<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebsiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchoolData;
use Tests\TestCase;

class AcademicCornerTest extends TestCase
{
    use BuildsSchoolData;
    use RefreshDatabase;

    public function test_the_academic_corner_page_uses_the_quick_card_manager(): void
    {
        $this->seedCore();

        $this->createCard('Class routine', '/class-routine', 1);

        $this->actingAs($this->admin())
            ->get(route('website-admin.academic-corner'))
            ->assertOk()
            ->assertSee('Academic cards')
            ->assertSee('Add academic card')
            ->assertSee('Class routine')
            ->assertSee(route('website-admin.academic-corner.store'), false);
    }

    public function test_administrator_can_add_a_card_that_appears_on_the_homepage(): void
    {
        $this->seedCore();

        $this->actingAs($this->admin())->post(route('website-admin.academic-corner.store'), [
            'icon' => 'graduation-cap',
            'title' => 'Academic programmes',
            'subtitle' => 'Classes, subjects and routines',
            'link_url' => '/academics',
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect(route('website-admin.academic-corner'));

        $card = WebsiteContent::query()->ofType('academic')->firstOrFail();

        $this->assertSame('academic', $card->type);
        $this->assertSame('Academic programmes', $card->title);
        $this->assertSame('Classes, subjects and routines', $card->subtitle);
        $this->assertSame('graduation-cap', $card->category);
        $this->assertSame('/academics', $card->link_url);
        $this->assertSame('published', $card->status);
        $this->assertNotNull($card->published_at);
        $this->assertSame('academic-programmes', $card->slug);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertSee('Academic programmes')
            ->assertSee('Classes, subjects and routines')
            ->assertSee('/academics', false)
            ->assertSee('M22 10 12 5 2 10l10 5 10-5Z', false);
    }

    public function test_cards_can_be_edited_reordered_disabled_and_deleted(): void
    {
        $this->seedCore();

        $first = $this->createCard('First card', '/first', 1);
        $second = $this->createCard('Second card', '/second', 2);

        $this->actingAs($this->admin())->put(route('website-admin.academic-corner.update', $second), [
            'icon' => 'book-open',
            'title' => 'Second card updated',
            'subtitle' => 'New sub title',
            'link_url' => '/downloads',
            'sort_order' => 2,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('Second card updated', $second->fresh()->title);
        $this->assertSame('book-open', $second->fresh()->category);

        $this->actingAs($this->admin())
            ->post(route('website-admin.academic-corner.move', $second->fresh()), ['direction' => 'up'])
            ->assertRedirect();

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);

        $this->actingAs($this->admin())->put(route('website-admin.academic-corner.update', $second), [
            'icon' => 'book-open',
            'title' => 'Second card updated',
            'subtitle' => 'New sub title',
            'link_url' => '/downloads',
            'sort_order' => 1,
            'is_active' => '0',
        ])->assertRedirect();

        $this->assertSame('draft', $second->fresh()->status);

        $this->get(route('website.home'))
            ->assertOk()
            ->assertDontSee('Second card updated');

        $this->actingAs($this->admin())
            ->delete(route('website-admin.academic-corner.destroy', $second))
            ->assertRedirect();

        $this->assertDatabaseMissing('website_contents', ['id' => $second->id]);
    }

    public function test_the_academic_corner_is_limited_to_four_cards(): void
    {
        $this->seedCore();

        foreach (range(1, 4) as $position) {
            $this->createCard('Card '.$position, '/card-'.$position, $position);
        }

        $this->actingAs($this->admin())->post(route('website-admin.academic-corner.store'), [
            'icon' => 'bell',
            'title' => 'Fifth card',
            'link_url' => '/notices',
            'sort_order' => 5,
            'is_active' => '1',
        ])->assertSessionHasErrors('title');

        $this->assertSame(4, WebsiteContent::query()->ofType('academic')->count());
    }

    public function test_cards_of_another_content_type_cannot_be_edited_through_the_manager(): void
    {
        $this->seedCore();

        $news = WebsiteContent::create([
            'type' => 'news',
            'title' => 'A news item',
            'slug' => 'a-news-item',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->put(route('website-admin.academic-corner.update', $news), [
                'icon' => 'bell',
                'title' => 'Hijacked',
                'link_url' => '/news',
                'is_active' => '1',
            ])
            ->assertNotFound();
    }

    private function createCard(string $title, string $link, int $sortOrder, array $overrides = []): WebsiteContent
    {
        return WebsiteContent::create(array_merge([
            'type' => 'academic',
            'title' => $title,
            'slug' => WebsiteContent::uniqueSlug($title),
            'category' => 'calendar',
            'link_url' => $link,
            'sort_order' => $sortOrder,
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    private function admin(): User
    {
        return User::query()->where('email', env('ADMIN_EMAIL', 'admin@edusphere.test'))->firstOrFail();
    }
}
