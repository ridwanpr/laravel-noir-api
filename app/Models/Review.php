<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id',
        'movie_id',
        'movie_title',
        'review_title',
        'review_body',
        'rating',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function watchlist()
    {
        return $this->belongsTo(Watchlist::class);
    }
}
