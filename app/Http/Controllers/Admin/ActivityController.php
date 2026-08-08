<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = ActivityLog::query()
            ->with(['user', 'actor'])
            ->when($request->filled('type'), fn ($q) => $q->where('subject_type', $request->string('type')))
            ->when($request->filled('gateway'), fn ($q) => $q->where('gateway', $request->string('gateway')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim($request->string('q')->toString());
                $q->where(function ($inner) use ($term): void {
                    $inner->where('description', 'like', '%'.$term.'%')
                        ->orWhere('subject_id', 'like', '%'.$term.'%')
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('email', 'like', '%'.$term.'%')
                            ->orWhere('name', 'like', '%'.$term.'%'));
                });
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.activities.index', compact('activities'));
    }
}
