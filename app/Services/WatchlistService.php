<?php

namespace App\Services;

use App\Models\Watchlist;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class WatchlistService
{
  public function countReviewed(Collection $watchlists, Collection $reviews): array
  {
    $reviewed = $watchlists->filter(fn($w) => $reviews->has($w->movie_id))->count();
    return [$reviewed, $watchlists->count() - $reviewed];
  }

  public function fetchWatchlists(Request $request, int $userId): array
  {
    $filter = $request->query('filter', 'all');
    $perPage = 10;
    $page = $request->query('page', 1);

    $allWatchlists = Watchlist::where('user_id', $userId)->latest()->get();
    $movieIds = $allWatchlists->pluck('movie_id')->all();

    $reviews = Review::whereIn('movie_id', $movieIds)
      ->select('movie_id', 'review_title', 'review_body', 'rating', 'id as review_id')
      ->get()
      ->keyBy('movie_id');

    [$reviewedCount, $notReviewedCount] = $this->countReviewed($allWatchlists, $reviews);

    $filtered = $allWatchlists->filter(function ($watchlist) use ($filter, $reviews) {
      $hasReview = $reviews->has($watchlist->movie_id);
      return match ($filter) {
        'reviewed' => $hasReview,
        'not_reviewed' => !$hasReview,
        default => true,
      };
    })->values();

    $paginated = new LengthAwarePaginator(
      $filtered->forPage($page, $perPage),
      $filtered->count(),
      $perPage,
      $page,
      ['path' => $request->url(), 'query' => $request->query()]
    );

    $paginated->getCollection()->transform(function ($watchlist) use ($reviews) {
      $watchlist->review = $reviews->get($watchlist->movie_id);
      return $watchlist;
    });

    return [
      'paginated' => $paginated,
      'reviewed_count' => $reviewedCount,
      'not_reviewed_count' => $notReviewedCount,
    ];
  }

  public function addToWatchlistWithOptionalReview(array $data, int $userId): array
  {
    DB::beginTransaction();

    $watchlist = Watchlist::create([
      'user_id' => $userId,
      'movie_id' => $data['movie_id'],
      'movie_title' => $data['movie_title'],
    ]);

    $hasReview = !empty($data['review_title']) || !empty($data['review_body']) || !empty($data['rating']);

    if ($hasReview) {
      Review::create([
        'user_id' => $userId,
        'movie_id' => $data['movie_id'],
        'movie_title' => $data['movie_title'],
        'review_title' => $data['review_title'] ?? null,
        'review_body' => $data['review_body'] ?? null,
        'rating' => $data['rating'] ?? null,
      ]);
    }

    DB::commit();

    return [
      'watchlist' => $watchlist,
      'message' => $hasReview ? 'Movie added to watchlist with review.' : 'Movie added to watchlist.'
    ];
  }

  public function updateWatchlistAndReview($data, $watchlistId, $reviewId)
  {
    $watchlist = Watchlist::find($watchlistId);
    if (!$watchlist) {
      return [
        'success' => false,
        'code' => 404,
        'message' => 'Watchlist not found.'
      ];
    }

    if (!empty($data['movie_title'])) {
      $watchlist->movie_title = $data['movie_title'];
      $watchlist->save();
    }

    $review = Review::find($reviewId);
    if ($review) {
      $review->update([
        'movie_title' => $data['movie_title'] ?? $review->movie_title,
        'review_title' => $data['review_title'] ?? $review->review_title,
        'review_body' => $data['review_body'] ?? $review->review_body,
        'rating' => $data['rating'] ?? $review->rating,
      ]);
    } else {
      Review::create([
        'user_id' => $watchlist->user_id,
        'movie_id' => $watchlist->movie_id,
        'movie_title' => $data['movie_title'] ?? $watchlist->movie_title,
        'review_title' => $data['review_title'] ?? null,
        'review_body' => $data['review_body'] ?? null,
        'rating' => $data['rating'] ?? null,
      ]);
    }

    return [
      'success' => true,
      'code' => 200,
      'message' => 'Review updated successfully.',
      'watchlist' => $watchlist,
      'review' => $review ?? Review::find($reviewId)
    ];
  }
}
