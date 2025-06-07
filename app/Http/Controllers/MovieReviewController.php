<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Watchlist;
use Illuminate\Http\Request;

class MovieReviewController extends Controller
{
    public function index(Request $request)
    {
        $movieReview = Review::with(['user:id,name'])
            ->where('movie_id', $request->id)->limit(6)
            ->limit(6)
            ->get();

        return response()->json([
            "success" => true,
            "message" => "Reviews fetched successfully.",
            "data" => $movieReview
        ]);
    }

    public function store(Request $request) {}

    public function update(Request $request, string $id) {}

    public function destroy(string $id) {}
}
