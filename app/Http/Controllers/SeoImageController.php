<?php

namespace App\Http\Controllers;

use App\Models\SiteMedia;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoImageController extends Controller
{
    public function __invoke(): Response
    {
        return $this->respond('meta_seo');
    }

    public function logo(): Response
    {
        return $this->respond('business_logo');
    }

    public function social(): Response
    {
        return $this->respond('meta_seo', true);
    }

    private function respond(string $key, bool $social = false): Response
    {
        $media = Cache::remember('site-media:'.$key, now()->addHour(), fn () => SiteMedia::query()->where('key', $key)->first());
        abort_unless($media, 404);

        $binary = base64_decode((string) $media->data_base64, true);
        abort_if($binary === false, 404);

        // URL media lokal selalu memakai query ?v=<timestamp> saat file berubah.
        // Karena itu aset dapat dicache lama. ETag tidak lagi menghitung hash
        // seluruh base64 pada setiap request sehingga logo lebih cepat muncul di HP.
        $etag = '"'.sha1($media->id.'|'.$media->updated_at?->getTimestamp().'|'.$media->mime_type).'"';

        $mime = $media->mime_type ?: ($social ? 'image/jpeg' : 'image/png');
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($binary),
            'Content-Disposition' => 'inline; filename="dapetotp-social.'.$extension.'"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => optional($media->updated_at)->toRfc7231String() ?: now()->toRfc7231String(),
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
