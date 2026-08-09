<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AnnouncementImageController extends Controller
{
    public function __invoke(Request $request, Announcement $announcement): Response
    {
        $path = ltrim((string) $announcement->image_path, '/');

        abort_if($path === '', 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $size = (int) $disk->size($path);
        $modified = (int) $disk->lastModified($path);
        $etag = '"'.sha1($path.'|'.$size.'|'.$modified).'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            ]);
        }

        $content = $disk->get($path);
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';

        return response($content, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modified).' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
