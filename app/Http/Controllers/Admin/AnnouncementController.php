<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View { return view('admin.announcements.index', ['announcements' => Announcement::query()->latest()->paginate(20)]); }
    public function create(): View { return view('admin.announcements.form', ['announcement' => new Announcement()]); }
    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $announcement = Announcement::query()->create(['created_by' => $request->user()->id, ...$this->validated($request)]);
        $audit->record('announcement.create', $announcement, [], $announcement->toArray());
        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman berhasil dibuat.');
    }
    public function edit(Announcement $announcement): View { return view('admin.announcements.form', compact('announcement')); }
    public function update(Request $request, Announcement $announcement, AuditService $audit): RedirectResponse
    {
        $before = $announcement->toArray(); $announcement->update($this->validated($request));
        $audit->record('announcement.update', $announcement, $before, $announcement->fresh()->toArray());
        return redirect()->route('admin.announcements.index')->with('success', 'Pengumuman diperbarui.');
    }
    public function destroy(Announcement $announcement, AuditService $audit): RedirectResponse
    {
        $audit->record('announcement.delete', $announcement, $announcement->toArray()); $announcement->delete();
        return back()->with('success', 'Pengumuman dihapus.');
    }
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000'],
            'type' => ['required', Rule::in(['info', 'success', 'warning', 'danger'])],
            'is_active' => ['nullable', 'boolean'], 'is_pinned' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]) + ['is_active' => $request->boolean('is_active'), 'is_pinned' => $request->boolean('is_pinned')];
    }
}
