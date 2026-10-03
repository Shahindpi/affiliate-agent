<?php

namespace App\Http\Controllers;

use App\Models\Post;

class RssFeedController extends Controller
{
    /**
     * Generate RSS feed.
     */
    public function index()
    {
        $posts = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('rss.index', compact('posts'))
            ->header(
                'Content-Type',
                'application/rss+xml; charset=UTF-8'
            );
    }
}