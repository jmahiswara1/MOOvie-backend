<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    private TmdbService $tmdb;

    public function __construct(TmdbService $tmdb)
    {
        $this->tmdb = $tmdb;
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('query', '');
        $page = (int) $request->query('page', 1);

        $data = $this->tmdb->search($query, $page);

        return response()->json([
            'success' => true,
            'data' => $data['results'] ?? [],
            'meta' => [
                'current_page' => $data['page'] ?? $page,
                'total_pages' => $data['total_pages'] ?? 0,
                'total_results' => $data['total_results'] ?? 0,
            ],
            'message' => 'OK',
        ]);
    }

    public function detail(int $id): JsonResponse
    {
        $data = $this->tmdb->getDetail($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Film tidak ditemukan',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'OK',
        ]);
    }

    public function getByCategory(string $category): JsonResponse
    {
        $allowedCategories = ['trending', 'top_rated', 'popular', 'now_playing', 'action', 'horror', 'comedy'];

        if (!in_array($category, $allowedCategories)) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak valid',
                'data' => [],
            ], 422);
        }

        $data = $this->tmdb->fetchCategory($category);

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'OK',
        ]);
    }
}
