<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request, \App\Services\TmdbService $tmdb): JsonResponse
    {
        $watchlists = $request->user()
            ->watchlists()
            ->latest()
            ->paginate(15);

        // Get TMDB data for these movie IDs
        $movieIds = collect($watchlists->items())->pluck('movie_id')->toArray();
        $tmdbMovies = collect($tmdb->getMoviesByIds($movieIds))->keyBy('id');

        // Combine DB watchlist data with TMDB data
        $enrichedItems = collect($watchlists->items())->map(function ($item) use ($tmdbMovies) {
            $movieData = $tmdbMovies->get($item->movie_id) ?? [];
            return array_merge(['watchlist_id' => $item->id], $movieData);
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $enrichedItems,
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
