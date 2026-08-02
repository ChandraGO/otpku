<?php
namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\View\View;
class AnnouncementController extends Controller
{
    public function index(): View { return view('user.announcements', ['announcements' => Announcement::visible()->orderByDesc('is_pinned')->latest()->paginate(20)]); }
}
