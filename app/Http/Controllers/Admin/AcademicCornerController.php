<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\WebsiteContent;
use App\Services\AuditLogService;
use App\Support\WebsiteIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The homepage academic corner is a small, fixed four-card grid. It is managed
 * with the same quick add / reorder / enable pattern as the featured menu, but
 * the cards stay in website_contents so the existing public query and content
 * library keep working.
 */
class AcademicCornerController extends Controller
{
    public const MAX_CARDS = 4;

    public function __construct(private readonly AuditLogService $audit) {}

    public function index(): View
    {
        return view('admin.website.academic-corner', [
            'cards' => WebsiteContent::query()
                ->ofType('academic')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'icons' => WebsiteIcons::CHOICES,
            'limit' => self::MAX_CARDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (WebsiteContent::query()->ofType('academic')->count() >= self::MAX_CARDS) {
            throw ValidationException::withMessages([
                'title' => 'The academic corner holds a maximum of '.self::MAX_CARDS.' cards. Delete one before adding another.',
            ]);
        }

        $data['type'] = 'academic';
        $data['slug'] = WebsiteContent::uniqueSlug($data['title']);
        $data['is_featured'] = false;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $data['published_by'] = $data['status'] === 'published' ? $request->user()->id : null;
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        if (! $request->filled('sort_order')) {
            $data['sort_order'] = (int) WebsiteContent::query()->ofType('academic')->max('sort_order') + 1;
        }

        $card = WebsiteContent::create($data);

        $this->audit->record(
            'academic_card_created',
            'Website',
            "Added academic corner card {$card->title}",
            $card,
            null,
            $card->only(['title', 'subtitle', 'category', 'link_url', 'sort_order', 'status'])
        );

        return redirect()->route('website-admin.academic-corner')->with('success', 'Academic card added.');
    }

    public function update(Request $request, WebsiteContent $card): RedirectResponse
    {
        $this->ensureAcademic($card);

        $data = $this->validated($request);
        $before = $card->only(['title', 'subtitle', 'category', 'link_url', 'sort_order', 'status']);

        DB::transaction(function () use ($card, $data, $request, $before) {
            $card->captureRevision($request->user()->id, 'Before academic corner edit');

            $data['updated_by'] = $request->user()->id;

            if ($data['status'] === 'published') {
                $data['published_by'] = $request->user()->id;
                $data['published_at'] = $card->published_at ?? now();
            }

            $card->update($data);

            $this->audit->record(
                'academic_card_updated',
                'Website',
                "Updated academic corner card {$card->title}",
                $card,
                $before,
                $card->only(['title', 'subtitle', 'category', 'link_url', 'sort_order', 'status'])
            );
        });

        return back()->with('success', 'Academic card updated.');
    }

    /**
     * Move a card one position up or down. Sort orders are renumbered so the
     * homepage grid stays deterministic even when several cards shared a value.
     */
    public function move(Request $request, WebsiteContent $card): RedirectResponse
    {
        $this->ensureAcademic($card);

        $direction = $request->input('direction') === 'up' ? 'up' : 'down';

        $siblings = WebsiteContent::query()
            ->ofType('academic')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $siblings->search(fn (WebsiteContent $sibling) => $sibling->id === $card->id);

        if ($index === false) {
            return back();
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= $siblings->count()) {
            return back()->with('success', $direction === 'up'
                ? 'This card is already first.'
                : 'This card is already last.');
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $position => $sibling) {
                $sibling->update(['sort_order' => $position + 1]);
            }
        });

        $this->audit->record(
            'academic_card_moved',
            'Website',
            "Moved academic corner card {$card->title} {$direction}",
            $card,
            null,
            ['direction' => $direction, 'sort_order' => $card->fresh()->sort_order]
        );

        return back()->with('success', 'Academic card order updated.');
    }

    public function destroy(WebsiteContent $card): RedirectResponse
    {
        $this->ensureAcademic($card);

        $payload = $card->only(['title', 'link_url', 'category']);
        $imagePath = $card->image_path;

        DB::transaction(fn () => $card->delete());

        if ($imagePath
            && ! WebsiteContent::query()->where('image_path', $imagePath)->exists()
            && ! Media::query()->where('path', $imagePath)->exists()) {
            Storage::disk('public')->delete($imagePath);
        }

        $this->audit->record(
            'academic_card_deleted',
            'Website',
            "Deleted academic corner card {$payload['title']}",
            null,
            $payload,
            null
        );

        return back()->with('success', 'Academic card deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'icon' => ['nullable', 'string', Rule::in(array_keys(WebsiteIcons::CHOICES))],
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'link_url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail) {
                if (! preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', (string) $value)) {
                    $fail('The link must be a relative path or an http(s), mailto or tel URL.');
                }
            }],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'category' => filled($data['icon'] ?? null) ? $data['icon'] : null,
            'link_url' => $data['link_url'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $request->boolean('is_active') ? 'published' : 'draft',
        ];
    }

    private function ensureAcademic(WebsiteContent $card): void
    {
        abort_unless($card->type === 'academic', 404);
    }
}
