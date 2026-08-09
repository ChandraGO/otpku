<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::query()->latest()->paginate(20),
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:ratio=16/9'],
            'remove_image' => ['nullable', 'boolean'],
            'type' => ['required', Rule::in(['info', 'important', 'news', 'update', 'deposit', 'service', 'success', 'warning', 'danger'])],
            'is_active' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        unset($data['image'], $data['remove_image']);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_pinned'] = $request->boolean('is_pinned');

        if ($request->hasFile('image')) {
            $this->deleteImage($announcement?->image_path);
            $data['image_path'] = $request->file('image')->store('announcements', 'public');
        } elseif ($request->boolean('remove_image') && $announcement) {
            $this->deleteImage($announcement->image_path);
            $data['image_path'] = null;
        }

        return $data;
    }

    private function deleteImage(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
