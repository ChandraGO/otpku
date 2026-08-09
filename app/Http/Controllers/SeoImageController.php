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
        $media = Cache::remember('site-media:meta_seo', now()->addHour(), fn () => SiteMedia::query()->where('key', 'meta_seo')->first());
        abort_unless($media, 404);

        $binary = base64_decode((string) $media->data_base64, true);
        abort_if($binary === false, 404);

        $updatedAt = $media->updated_at?->getTimestamp() ?: 0;
        $cacheKey = 'site-media:meta_seo:social:1200x630:v'.$media->id.':'.$updatedAt;

        $social = Cache::remember($cacheKey, now()->addDay(), function () use ($binary): array {
            return $this->normalizeSocialImage($binary);
        });

        $payload = $social['binary'] ?? $binary;
        $mime = $social['mime'] ?? ($media->mime_type ?: 'image/jpeg');
        $width = (int) ($social['width'] ?? 0);
        $height = (int) ($social['height'] ?? 0);
        $etag = '"'.sha1($media->id.'|'.$updatedAt.'|'.$mime.'|'.strlen($payload)).'"';

        return response($payload, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($payload),
            // Tidak memakai attachment/Content-Disposition agar crawler sosial
            // memperlakukan endpoint ini sebagai aset gambar biasa.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => optional($media->updated_at)->toRfc7231String() ?: now()->toRfc7231String(),
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
            'X-Social-Image-Width' => (string) $width,
            'X-Social-Image-Height' => (string) $height,
        ]);
    }

    private function respond(string $key): Response
    {
        $media = Cache::remember('site-media:'.$key, now()->addHour(), fn () => SiteMedia::query()->where('key', $key)->first());
        abort_unless($media, 404);

        $binary = base64_decode((string) $media->data_base64, true);
        abort_if($binary === false, 404);

        // URL media lokal selalu memakai query ?v=<timestamp> saat file berubah.
        // Karena itu aset dapat dicache lama. ETag tidak lagi menghitung hash
        // seluruh base64 pada setiap request sehingga logo lebih cepat muncul di HP.
        $etag = '"'.sha1($media->id.'|'.$media->updated_at?->getTimestamp().'|'.$media->mime_type).'"';

        $mime = $media->mime_type ?: 'image/png';
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($binary),
            'Content-Disposition' => 'inline; filename="dapetotp-media.'.$extension.'"',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => optional($media->updated_at)->toRfc7231String() ?: now()->toRfc7231String(),
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Normalisasi output OG khusus sosial menjadi JPEG 1200x630 dan berusaha
     * menjaga ukuran file ringan. WhatsApp/jejaring sosial tetap menentukan
     * sendiri layout preview, tetapi endpoint ini memastikan aset yang mereka
     * baca memang landscape 1.91:1 dan bukan file lama/square.
     *
     * @return array{binary:string,mime:string,width:int,height:int}
     */
    private function normalizeSocialImage(string $binary): array
    {
        $fallbackInfo = @getimagesizefromstring($binary) ?: [];
        $fallback = [
            'binary' => $binary,
            'mime' => (string) ($fallbackInfo['mime'] ?? 'image/jpeg'),
            'width' => (int) ($fallbackInfo[0] ?? 0),
            'height' => (int) ($fallbackInfo[1] ?? 0),
        ];

        if (! function_exists('imagecreatefromstring')
            || ! function_exists('imagecreatetruecolor')
            || ! function_exists('imagecopyresampled')
            || ! function_exists('imagejpeg')) {
            return $fallback;
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return $fallback;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);
            return $fallback;
        }

        $targetWidth = 1200;
        $targetHeight = 630;
        $targetRatio = $targetWidth / $targetHeight;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            // Sumber terlalu lebar: crop kiri/kanan secara simetris.
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $srcX = (int) floor(($sourceWidth - $cropWidth) / 2);
            $srcY = 0;
        } else {
            // Sumber terlalu tinggi: crop atas/bawah secara simetris.
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $srcX = 0;
            $srcY = (int) floor(($sourceHeight - $cropHeight) / 2);
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($canvas === false) {
            imagedestroy($source);
            return $fallback;
        }

        $background = imagecolorallocate($canvas, 15, 23, 42);
        imagefill($canvas, 0, 0, $background);
        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $srcX,
            $srcY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight,
        );
        imageinterlace($canvas, true);

        $output = null;
        // Mulai dari kualitas bagus, lalu kecilkan bila perlu. Target ~300 KB
        // membantu crawler sosial mengambil gambar dengan cepat di koneksi lambat.
        foreach ([84, 80, 76, 72, 68, 64] as $quality) {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $candidate = (string) ob_get_clean();
            if ($candidate === '') {
                continue;
            }
            $output = $candidate;
            if (strlen($candidate) <= 300 * 1024) {
                break;
            }
        }

        imagedestroy($canvas);
        imagedestroy($source);

        if (! is_string($output) || $output === '') {
            return $fallback;
        }

        return [
            'binary' => $output,
            'mime' => 'image/jpeg',
            'width' => $targetWidth,
            'height' => $targetHeight,
        ];
    }
}
