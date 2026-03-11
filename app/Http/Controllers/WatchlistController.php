<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $watchlists = $request->user()
            ->watchlists()
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $watchlists->items(),
            'meta' => [
                'current_page' => $watchlists->currentPage(),
                'last_page' => $watchlists->lastPage(),
                'per_page' => $watchlists->perPage(),
                'total' => $watchlists->total(),
            ],
            'message' => 'OK',
        ]);
    }

    public function ids(Request $request): JsonResponse
    {
        $ids = $request->user()
            ->watchlists()
            ->pluck('movie_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $ids,
            'message' => 'OK',
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'movie_id' => 'required|integer',
        ]);

        $movieId = $request->input('movie_id');
        $user = $request->user();

        $existing = $user->watchlists()
            ->where('movie_id', $movieId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'success' => true,
                'data' => ['action' => 'removed', 'movie_id' => $movieId],
                'message' => 'Dihapus dari watchlist',
            ]);
        }

        $user->watchlists()->create(['movie_id' => $movieId]);

        return response()->json([
            'success' => true,
            'data' => ['action' => 'added', 'movie_id' => $movieId],
            'message' => 'Ditambahkan ke watchlist',
        ]);
    }
}
