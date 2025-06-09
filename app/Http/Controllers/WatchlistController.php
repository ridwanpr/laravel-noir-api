<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\WatchlistService;

class WatchlistController extends Controller
{
    protected WatchlistService $watchlistService;

    public function __construct(WatchlistService $watchlistService)
    {
        $this->watchlistService = $watchlistService;
    }

    public function index(Request $request)
    {
        $userId = Auth::id();
        $result = $this->watchlistService->fetchWatchlists($request, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Watchlist fetched successfully.',
            'data' => $result['paginated'],
            'reviewed_count' => $result['reviewed_count'],
            'not_reviewed_count' => $result['not_reviewed_count'],
        ]);
    }

    public function store(Request $request)
    {
        $userId = Auth::id();

        $request->validate([
            'movie_id' => 'required|integer',
            'movie_title' => 'required|string|max:255',
            'review_title' => 'nullable|string|max:255',
            'review_body' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5'
        ]);

        if (Watchlist::where('user_id', $userId)->where('movie_id', $request->movie_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This movie is already in your watchlist.'
            ], 409);
        }

        if (Review::where('user_id', $userId)->where('movie_id', $request->movie_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this movie.'
            ], 409);
        }

        try {
            $result = $this->watchlistService->addToWatchlistWithOptionalReview($request->all(), $userId);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'watchlist_id' => $result['watchlist']->id,
                    'movie_id' => $result['watchlist']->movie_id,
                    'movie_title' => $result['watchlist']->movie_title
                ]
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add movie to watchlist: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $watchlistId, $reviewId)
    {
        $request->validate([
            'movie_title' => 'nullable|string|max:255',
            'review_title' => 'nullable|string|max:255',
            'review_body' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $result = $this->watchlistService->updateWatchlistAndReview($request->all(), $watchlistId, $reviewId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'watchlist' => $result['watchlist'],
                'review' => $result['review']
            ]
        ]);
    }

    public function destroy($watchlistId, $reviewId)
    {
        $result = $this->watchlistService->deleteWatchlistAndReview($watchlistId, $reviewId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message']
        ]);
    }
}
