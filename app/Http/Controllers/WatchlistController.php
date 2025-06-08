<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Watchlist;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreWatchlistRequest;
use App\Http\Requests\UpdateWatchlistRequest;
use Illuminate\Pagination\LengthAwarePaginator;

class WatchlistController extends Controller
{
    private function countReviewed($watchlists, $reviews)
    {
        $reviewedCount = 0;
        $notReviewedCount = 0;

        foreach ($watchlists as $watchlist) {
            if ($reviews->has($watchlist->movie_id)) {
                $reviewedCount++;
            } else {
                $notReviewedCount++;
            }
        }

        return [$reviewedCount, $notReviewedCount];
    }
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $perPage = 10;
        $page = $request->query('page', 1);

        $allWatchlists = Watchlist::where('user_id', auth()->user()->id)->latest()->get();
        $movieIds = $allWatchlists->pluck('movie_id')->all();
        $reviews = Review::whereIn('movie_id', $movieIds)
            ->select('movie_id', 'review_title', 'review_body', 'rating')
            ->get()
            ->keyBy('movie_id');

        [$reviewedCount, $notReviewedCount] = $this->countReviewed($allWatchlists, $reviews);

        $filteredWatchlists = $allWatchlists->filter(function ($watchlist) use ($reviews, $filter) {
            $hasReview = $reviews->has($watchlist->movie_id);
            if ($filter === 'reviewed') {
                return $hasReview;
            }
            if ($filter === 'not_reviewed') {
                return !$hasReview;
            }
            return true; // all
        })->values();

        $paginatedWatchlists = new LengthAwarePaginator(
            $filteredWatchlists->forPage($page, $perPage),
            $filteredWatchlists->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Attach reviews to each watchlist item in current page
        $paginatedWatchlists->getCollection()->transform(function ($watchlist) use ($reviews) {
            $watchlist->review = $reviews->get($watchlist->movie_id);
            return $watchlist;
        });

        return response()->json([
            'success' => true,
            'message' => 'Watchlist fetched successfully.',
            'data' => $paginatedWatchlists,
            'reviewed_count' => $reviewedCount,
            'not_reviewed_count' => $notReviewedCount,
        ]);
    }

    public function store(Request $request)
    {
        $userId = auth()->user()->id;

        $request->validate([
            'movie_id' => 'required|integer',
            'movie_title' => 'required|string|max:255',
            'review_title' => 'nullable|string|max:255',
            'review_body' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5'
        ]);

        $existingWatchlist = Watchlist::where('user_id', $userId)
            ->where('movie_id', $request->movie_id)
            ->first();

        if ($existingWatchlist) {
            return response()->json([
                'success' => false,
                'message' => 'This movie is already in your watchlist.'
            ], 409);
        }

        $existingReview = Review::where('user_id', $userId)
            ->where('movie_id', $request->movie_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this movie.'
            ], 409);
        }

        DB::beginTransaction();
        try {
            $watchlist = Watchlist::create([
                'user_id' => $userId,
                'movie_id' => $request->movie_id,
                'movie_title' => $request->movie_title
            ]);

            $hasReviewData = $request->filled(['review_title']) ||
                $request->filled(['review_body']) ||
                $request->filled(['rating']);

            if ($hasReviewData) {
                Review::create([
                    'user_id' => $userId,
                    'movie_id' => $request->movie_id,
                    'movie_title' => $request->movie_title,
                    'review_title' => $request->review_title,
                    'review_body' => $request->review_body,
                    'rating' => $request->rating
                ]);

                $message = 'Movie added to watchlist with review.';
            } else {
                $message = 'Movie added to watchlist.';
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'watchlist_id' => $watchlist->id,
                    'movie_id' => $watchlist->movie_id,
                    'movie_title' => $watchlist->movie_title
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add movie to watchlist: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Watchlist $watchlist) {}

    public function update(Request $request, $watchlistId, $reviewId)
    {
        $watchlist = Watchlist::find($watchlistId);

        if (!$watchlist) {
            return response()->json([
                'success' => false,
                'message' => 'Watchlist not found.'
            ], 404);
        }

        $review = Review::find($reviewId);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully.',
            'data' => [
                'watchlist_id' => $watchlistId,
                'review_id' => $reviewId
            ]
        ]);
    }

    public function destroy(Watchlist $watchlist) {}
}
