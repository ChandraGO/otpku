<?php
namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request): View
    {
        return view('user.wallet', ['transactions' => WalletTransaction::query()->where('user_id', $request->user()->id)->latest()->paginate(25)]);
    }
}
