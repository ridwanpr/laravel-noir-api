<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MovieReviewController;
use App\Http\Controllers\WatchlistController;

Route::get('/user', function (Request $request) {
  return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::apiResource('watchlist', WatchlistController::class)->middleware('auth:sanctum');
Route::get('reviews/{id}', [MovieReviewController::class, 'index']);
Route::put('watchlist/{watchlist_id}/{review_id}', [WatchlistController::class, 'update'])->middleware('auth:sanctum');