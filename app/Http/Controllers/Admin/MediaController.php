<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\SchoolSetting;
use App\Models\WebsiteContent;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): View
    {
        $media = Media::query()
            ->with('uploader')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($q) => $q
                ->where('original_name', 'like', '%'.$request->string('q').'%')
                ->orWhere('alt_text', 'like', '%'.$request->string('q').'%')
                ->orWhere('caption', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('kind'), fn ($query) => $query->where('mime_type', 'like', $request->string('kind').'/%'))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        return view('admin.website.media', [
            'media' => $media,
            'categories' => Media::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt,csv',
            ],
            'category' => ['nullable', 'string', 'max:80'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $created = 0;
        $failed = [];

        DB::transaction(function () use ($request, &$created, &$failed) {
            foreach ($request->file('files', []) as $file) {
                try {
                    $path = $file->store('website/media', 'public');
                    $mime = $file->getMimeType() ?: 'application/octet-stream';
                    $width = null;
                    $height = null;

                    if (str_starts_with($mime, 'image/')) {
                        $dimensions = @getimagesize(Storage::disk('public')->path($path));
                        if (is_array($dimensions)) {
                            $width = $dimensions[0] ?? null;
                            $height = $dimensions[1] ?? null;
                        }
                    }

                    Media::create([
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $mime,
                        'extension' => strtolower($file->getClientOriginalExtension()),
                        'size' => $file->getSize() ?: 0,
                        'width' => $width,
                        'height' => $height,
                        'alt_text' => $request->string('alt_text')->toString() ?: null,
                        'category' => $request->string('category')->toString() ?: null,
                        'uploaded_by' => $request->user()->id,
                    ]);

                    $created++;
                } catch (\Throwable $exception) {
                    $failed[] = $file->getClientOriginalName();
                }
            }
        });

        $this->audit->record(
            'media_uploaded', 'Website',
            "Uploaded {$created} media file(s)", null, null,
            ['count' => $created, 'failed' => $failed]
        );

        $message = "{$created} file(s) uploaded.";
        if ($failed !== []) {
            $message .= ' Failed: '.implode(', ', $failed);
        }

        return redirect()->route('media.index')->with($created > 0 ? 'success' : 'error', $message);
    }

    public function update(Request $request, Media $medium): RedirectResponse
    {
        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $before = $medium->only(['alt_text', 'caption', 'category']);
        $medium->update($data);

        $this->audit->record('media_updated', 'Website', "Updated media metadata: {$medium->original_name}", $medium, $before, $data);

        return back()->with('success', 'Media details updated.');
    }

    public function destroy(Media $medium): RedirectResponse
    {
        $path = $medium->path;
        $payload = $medium->only(['path', 'original_name', 'mime_type', 'size']);

        if (WebsiteContent::query()
            ->where(fn ($query) => $query->where('image_path', $path)
                ->orWhere('og_image_path', $path)
                ->orWhere('file_path', $path))
            ->exists()) {
            return back()->with('error', 'This file is used by website content. Remove or replace it there before deleting it from the media library.');
        }

        if (SchoolSetting::query()->where('value', $path)->exists()) {
            return back()->with('error', 'This file is used in website settings and cannot be deleted here.');
        }

        DB::transaction(function () use ($medium) {
            $medium->delete();
        });

        Storage::disk('public')->delete($path);
        $this->audit->record('media_deleted', 'Website', "Deleted media: {$payload['original_name']}", null, $payload, null);

        return back()->with('success', 'Media file deleted.');
    }
}
