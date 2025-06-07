<?php

namespace App\Http\Controllers;

use App\Models\Watchlist;
use Illuminate\Http\Request;

class MovieReviewController extends Controller
{
    public function index() {}

    public function store(Request $request) {}

    public function show(string $id)
    {
        $watchlist = Watchlist::with(['user:id,name'])
            ->where('movie_id', $id)->limit(6)
            ->get();

        return [
            "success" => true,
            "message" => "Watchlist fetched successfully.",
            "data" => $watchlist
        ];
    }

    public function update(Request $request, string $id) {}

    public function destroy(string $id) {}
}
