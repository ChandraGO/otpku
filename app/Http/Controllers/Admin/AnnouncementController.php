<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::query()
                ->orderByDesc('is_pinned')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement()]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $announcement = Announcement::query()->create([
            'created_by' => $request->user()->id,
            ...$this->validated($request),
        ]);

        $audit->record('announcement.create', $announcement, [], $announcement->toArray());

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil dibuat.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement, AuditService $audit): RedirectResponse
    {
        $before = $announcement->toArray();
        $announcement->update($this->validated($request, $announcement));
        $audit->record('announcement.update', $announcement, $before, $announcement->fresh()->toArray());

        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman diperbarui.');
    }

    public function destroy(Announcement $announcement, AuditService $audit): RedirectResponse
    {
        $audit->record('announcement.delete', $announcement, $announcement->toArray());
        $this->deleteImage($announcement->image_path);
        $announcement->delete();

        return back()->with('success', 'Pengumuman dihapus.');
    }

    private function validated(Request $request, ?Announcement $announcement = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            // File mentah bebas rasio. Editor browser dapat menghasilkan banner, landscape,
            // persegi, portrait, story, atau ukuran custom sebelum gambar disimpan.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cropped_image' => ['nullable', 'string'],
            'remove_image' => ['nullable', 'boolean'],
            'type' => ['required', Rule::in(['info', 'important', 'news', 'update', 'deposit', 'service', 'success', 'warning', 'danger'])],
            'is_active' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        unset($data['image'], $data['cropped_image'], $data['remove_image']);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_pinned'] = $request->boolean('is_pinned');

        if ($request->filled('cropped_image')) {
            $this->deleteImage($announcement?->image_path);
            $data['image_path'] = $this->storeCroppedImage((string) $request->input('cropped_image'));
        } elseif ($request->hasFile('image')) {
            // Fallback jika browser tidak mendukung editor crop. Gambar asli tetap disimpan
            // dan tampilan publik mempertahankan rasio intrinsiknya.
            $this->deleteImage($announcement?->image_path);
            $storedPath = $request->file('image')->store('announcements', 'public');
            if (! is_string($storedPath) || $storedPath === '' || ! Storage::disk('public')->exists($storedPath)) {
                throw ValidationException::withMessages([
                    'image' => 'Gambar gagal disimpan ke penyimpanan server. Silakan coba lagi setelah beberapa saat.',
                ]);
            }
            $data['image_path'] = $storedPath;
        } elseif ($request->boolean('remove_image') && $announcement) {
            $this->deleteImage($announcement->image_path);
            $data['image_path'] = null;
        }

        return $data;
    }

    private function storeCroppedImage(string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+\/=\r\n]+)$/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'image' => 'Hasil crop gambar tidak valid. Silakan pilih gambar kembali.',
            ]);
        }

        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]), true);

        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'image' => 'Hasil crop gambar terlalu besar atau tidak valid.',
            ]);
        }

        $size = @getimagesizefromstring($binary);
        $width = (int) ($size[0] ?? 0);
        $height = (int) ($size[1] ?? 0);
        $ratio = $height > 0 ? $width / $height : 0;

        if (! is_array($size) || $width < 240 || $height < 240 || $width > 2400 || $height > 2400) {
            throw ValidationException::withMessages([
                'image' => 'Resolusi hasil crop harus antara 240 sampai 2400 px pada setiap sisi.',
            ]);
        }

        if (($width * $height) > 6000000 || $ratio < 0.25 || $ratio > 4) {
            throw ValidationException::withMessages([
                'image' => 'Resolusi atau rasio hasil crop terlalu besar. Gunakan maksimum 6 megapiksel dan rasio antara 1:4 sampai 4:1.',
            ]);
        }

        $path = 'announcements/'.Str::uuid().'.jpg';
        $stored = Storage::disk('public')->put($path, $binary);

        // Disk public memakai throw=false. Tanpa pengecekan ini kegagalan write
        // dapat terlihat seperti sukses: image_path masuk DB tetapi file tidak ada,
        // lalu browser mendapatkan 404.
        if (! $stored || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'image' => 'Gambar gagal disimpan ke penyimpanan server. Silakan coba lagi setelah beberapa saat.',
            ]);
        }

        return $path;
    }

    private function deleteImage(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
