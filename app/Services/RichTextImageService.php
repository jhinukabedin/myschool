<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores images inserted from a rich text editor and registers them in the
 * media library. Both the website content manager and the notice manager use
 * the same endpoint behaviour so inline images look identical everywhere.
 */
class RichTextImageService
{
    /**
     * @return array{path:string,url:string}
     */
    public function store(UploadedFile $file, ?int $userId, string $directory = 'website/images'): array
    {
        $path = $file->store($directory, 'public');
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        $width = null;
        $height = null;
        $dimensions = @getimagesize(Storage::disk('public')->path($path));
        if (is_array($dimensions)) {
            $width = $dimensions[0] ?? null;
            $height = $dimensions[1] ?? null;
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
            'uploaded_by' => $userId,
        ]);

        return [
            'path' => $path,
            'url' => asset('storage/'.$path),
        ];
    }
}
