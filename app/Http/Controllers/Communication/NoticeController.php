<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNoticeRequest;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Services\AuditLogService;
use App\Services\RichTextImageService;
use App\Services\WebsiteHtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $notices = Notice::query()
            ->with(['creator', 'classes'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.$request->input('q').'%'))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('notices.index', [
            'notices' => $notices,
            'counts' => Notice::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('notices.form', [
            'notice' => new Notice([
                'audience' => 'all',
                'status' => 'draft',
                'publish_at' => now()->format('Y-m-d\TH:i'),
            ]),
            'classes' => SchoolClass::ordered()->get(),
        ]);
    }

    public function store(StoreNoticeRequest $request): RedirectResponse
    {
        $data = $this->prepareBody($request->validated());
        $classes = $data['classes'] ?? [];
        unset($data['classes']);
        $data['created_by'] = $request->user()->id;

        $notice = DB::transaction(function () use ($data, $classes) {
            $notice = Notice::create($data);

            if ($notice->audience === 'classes') {
                $notice->classes()->sync($classes);
            }

            $this->audit->record('notice_created', 'Communication', "Notice “{$notice->title}” created", $notice, null, $data);

            return $notice;
        });

        return redirect()->route('notices.index')->with('success', 'Notice saved.');
    }

    public function edit(Notice $notice): View
    {
        return view('notices.form', [
            'notice' => $notice->load('classes'),
            'classes' => SchoolClass::ordered()->get(),
            'selectedClasses' => $notice->classes->pluck('id')->all(),
        ]);
    }

    public function update(StoreNoticeRequest $request, Notice $notice): RedirectResponse
    {
        $data = $this->prepareBody($request->validated());
        $classes = $data['classes'] ?? [];
        unset($data['classes']);
        $before = $notice->getAttributes();

        DB::transaction(function () use ($notice, $data, $classes, $before) {
            $notice->update($data);
            $notice->classes()->sync($notice->audience === 'classes' ? $classes : []);

            $this->audit->record(
                'notice_updated',
                'Communication',
                "Notice “{$notice->title}” updated",
                $notice,
                $before,
                $notice->fresh()->getAttributes()
            );
        });

        return redirect()->route('notices.index')->with('success', 'Notice updated.');
    }

    /**
     * Images inserted from the notice body editor. Permission is checked on
     * the route, so notice managers do not need website permissions.
     */
    public function uploadImage(Request $request, RichTextImageService $images): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $stored = $images->store($request->file('image'), $request->user()->id);

        return response()->json(['url' => $stored['url']]);
    }

    /**
     * Sanitise an HTML body and reject an editor that produced only empty
     * markup. Plain text bodies are stored exactly as typed.
     */
    private function prepareBody(array $data): array
    {
        $format = $data['body_format'] ?? 'text';
        $data['body_format'] = $format;

        if ($format !== 'html') {
            return $data;
        }

        $clean = app(WebsiteHtmlSanitizer::class)->sanitize($data['body']);

        if (trim(strip_tags($clean)) === '' && ! str_contains($clean, '<img')) {
            throw ValidationException::withMessages([
                'body' => 'Write the notice body before saving.',
            ]);
        }

        $data['body'] = $clean;

        return $data;
    }

    public function publish(Notice $notice): RedirectResponse
    {
        $before = $notice->only(['status', 'publish_at']);
        $notice->update([
            'status' => 'published',
            'publish_at' => $notice->publish_at ?? now(),
        ]);

        $this->audit->record('notice_published', 'Communication', "Notice “{$notice->title}” published", $notice, $before, ['status' => 'published']);

        return back()->with('success', 'Notice published.');
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $old = $notice->getAttributes();
        $notice->delete();
        $this->audit->record('notice_deleted', 'Communication', "Notice “{$notice->title}” deleted", null, $old, null);

        return back()->with('success', 'Notice deleted.');
    }
}
