<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DatabaseBackup;
use App\Services\AuditService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function index()
    {
        return view('admin.backups.index', ['backups' => DatabaseBackup::query()->latest()->paginate(20)]);
    }
    public function create(Request $request, BackupService $service, AuditService $audit): RedirectResponse
    {
        try { $backup = $service->create($request->user()->id); $audit->record('backup.create', $backup); return back()->with('success', 'Backup database berhasil dibuat.'); }
        catch (Throwable $e) { return back()->withErrors(['backup' => $e->getMessage()]); }
    }
    public function upload(Request $request, BackupService $service, AuditService $audit): RedirectResponse
    {
        $max = (int) config('otp.backup_max_mb', 100) * 1024;
        $data = $request->validate(['backup' => ['required', 'file', 'max:'.$max, 'mimes:sql,gz,txt']]);
        try { $backup = $service->storeUpload($data['backup'], $request->user()->id); $audit->record('backup.upload', $backup); return back()->with('success', 'File backup berhasil diunggah.'); }
        catch (Throwable $e) { return back()->withErrors(['backup' => $e->getMessage()]); }
    }
    public function download(DatabaseBackup $backup): BinaryFileResponse
    {
        return response()->download(Storage::disk($backup->disk)->path($backup->filename), $backup->filename);
    }
    public function restore(Request $request, DatabaseBackup $backup, BackupService $service, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['confirmation' => ['required', 'in:RESTORE'], 'password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) return back()->withErrors(['backup' => 'Password admin tidak sesuai.']);
        try { $service->restore($backup); $audit->record('backup.restore', $backup); return back()->with('success', 'Database berhasil dipulihkan. Login ulang mungkin diperlukan.'); }
        catch (Throwable $e) { return back()->withErrors(['backup' => $e->getMessage()]); }
    }
    public function destroy(DatabaseBackup $backup, BackupService $service, AuditService $audit): RedirectResponse
    {
        $audit->record('backup.delete', $backup, $backup->toArray()); $service->delete($backup);
        return back()->with('success', 'Backup dihapus.');
    }
}
