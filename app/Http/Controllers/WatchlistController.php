<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use App\Http\Requests\StoreWatchlistRequest;
use App\Http\Requests\UpdateWatchlistRequest;
use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

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

        $allWatchlists = Watchlist::where('user_id', auth()->user()->id)->get();
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

    public function store(StoreWatchlistRequest $request)
    {
        $request->validate([
            'movie_id' => 'required',
        ]);
        Watchlist::create($request);

        return [
            'message' => 'Watchlist created successfully.'
        ];
    }

    public function show(Watchlist $watchlist) {}

    public function update(UpdateWatchlistRequest $request, Watchlist $watchlist) {}

    public function destroy(Watchlist $watchlist) {}
}
