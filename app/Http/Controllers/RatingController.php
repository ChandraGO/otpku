<?php

namespace App\Http\Controllers;

use App\Models\OtpOrder;
use App\Models\Rating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatingController extends Controller
{
    public function index(Request $request): View
    {
        $ratings = Rating::query()
            ->with('user:id,name,username,email,github_id')
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString();

        $ratingStats = Rating::query()
            ->selectRaw('COUNT(*) as aggregate_count, AVG(rating) as aggregate_average')
            ->first();
        $ratingCount = (int) ($ratingStats?->aggregate_count ?? 0);
        $ratingAverage = $ratingCount > 0
            ? round((float) ($ratingStats?->aggregate_average ?? 0), 1)
            : 0.0;

        $canRate = false;
        $userRating = null;

        if ($request->user()) {
            $canRate = OtpOrder::query()
                ->where('user_id', $request->user()->id)
                ->where('status', 'completed')
                ->exists();

            $userRating = Rating::query()
                ->where('user_id', $request->user()->id)
                ->first();
        }

        return view('ratings.index', compact(
            'ratings',
            'ratingCount',
            'ratingAverage',
            'canRate',
            'userRating',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $eligible = OtpOrder::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->exists();

        if (! $eligible) {
            return redirect()
                ->route('ratings.index')
                ->withErrors([
                    'rating' => 'Rating hanya dapat diberikan setelah Anda menyelesaikan minimal 1 transaksi OTP.',
                ]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'review' => ['required', 'string', 'min:10', 'max:1200'],
        ], [
            'rating.required' => 'Pilih jumlah bintang terlebih dahulu.',
            'rating.between' => 'Rating harus antara 1 sampai 5 bintang.',
            'review.required' => 'Tuliskan pengalaman Anda menggunakan layanan.',
            'review.min' => 'Review minimal 10 karakter.',
            'review.max' => 'Review maksimal 1.200 karakter.',
        ]);

        Rating::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'rating' => (int) $validated['rating'],
                'review' => trim((string) $validated['review']),
            ],
        );

        return redirect()
            ->route('ratings.index')
            ->with('success', 'Rating dan review Anda berhasil disimpan. Terima kasih.');
    }
}
