<?php

namespace App\Http\Controllers;

use App\Models\SiteMedia;
use Illuminate\Http\Response;

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

    private function respond(string $key): Response
    {
        $media = SiteMedia::query()->where('key', $key)->first();
        abort_unless($media, 404);

        $binary = base64_decode((string) $media->data_base64, true);
        abort_if($binary === false, 404);

        // URL media lokal selalu memakai query ?v=<timestamp> saat file berubah.
        // Karena itu aset dapat dicache lama. ETag tidak lagi menghitung hash
        // seluruh base64 pada setiap request sehingga logo lebih cepat muncul di HP.
        $etag = '"'.sha1($media->id.'|'.$media->updated_at?->getTimestamp().'|'.$media->mime_type).'"';

        return response($binary, 200, [
            'Content-Type' => $media->mime_type ?: 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
