<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;

use App\Http\Requests\StoreCommentRequest;

use App\Http\Resources\Api\CommentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use App\Services\CacheService;

use App\Models\Post;
use App\Models\Comment;

use App\Support\ApiResponse;

class CommentController extends Controller
{

    /**
     * Approved comments for a post.
     */
    public function index(
        Request $request,
        string $slug
    ): JsonResponse {

        $post = Post::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (! $post) {
            return ApiResponse::error(
                'Post not found.',
                null, 404
            );
        }

        $comments = Cache::remember(
            CacheService::publicCommentsKey($slug),
            now()->addMinutes(30),
            function () use ($post, $request) {

                return Comment::query()

                    ->where('post_id', $post->id)

                    ->where('status', 'approved')

                    ->whereNull('parent_id')

                    ->with([
                        'replies' => function ($query) {
                            $query
                                ->where('status', 'approved')
                                ->orderBy('created_at');
                        }
                    ])

                    ->latest()

                    ->paginate(
                        min(max((int) $request->input('per_page', 10), 1), 50)
                    );
            }
        );

        return response()->json([

            'success' => true,

            'message' => 'Comments retrieved successfully.',

            'data' => [
                'comment_count' => $comments->total(),
                'comments' => CommentResource::collection(
                    $comments->items()
                ),
            ],

            'links' => [
                'first' => $comments->url(1),
                'last' => $comments->url($comments->lastPage()),
                'prev' => $comments->previousPageUrl(),
                'next' => $comments->nextPageUrl(),
            ],

            'meta' => [
                'current_page' => $comments->currentPage(),
                'from' => $comments->firstItem(),
                'last_page' => $comments->lastPage(),
                'path' => $comments->path(),
                'per_page' => $comments->perPage(),
                'to' => $comments->lastItem(),
                'total' => $comments->total(),
            ],

        ]);
    }

    /**
     * Store a new comment.
     */
    public function store(
        StoreCommentRequest $request,
        string $slug
    ): JsonResponse {

        $post = Post::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->first();

        if (! $post) {

            return ApiResponse::error(
                'Post not found.',
                null, 404
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Comments Disabled
        |--------------------------------------------------------------------------
        */

        if (! $post->allow_comments) {

            return ApiResponse::error(
                'Comments are disabled for this post.',
                null, 403
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Parent Comment
        |--------------------------------------------------------------------------
        */

        if ($request->filled('parent_id')) {

            $parent = Comment::where('id', $request->parent_id)
                ->where('post_id', $post->id)
                ->first();

            if (! $parent) {

                return ApiResponse::error(
                    'Invalid parent comment.',
                    null, 422
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Comment
        |--------------------------------------------------------------------------
        */

        $comment = Comment::create([

            'post_id' => $post->id,

            'parent_id' => $request->parent_id,

            'name' => trim($request->name),

            'email' => strtolower(trim($request->email)),

            'website' => $request->website,

            'comment' => trim($request->comment),

            'status' => 'pending',

            'ip_address' => $request->ip(),

            'user_agent' => $request->userAgent(),

        ]);

        CacheService::clearComments($post->slug);
        CacheService::clearCommentStatistics();
        
        return ApiResponse::success(

            CommentResource::make($comment),

            'Comment submitted successfully. It is awaiting approval.',

            201

        );
    }
}