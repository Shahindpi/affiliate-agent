<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Comment;
use App\Http\Resources\Api\CommentResource;
use App\Services\CacheService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class CommentController extends Controller
{
    /**
     * Comment listing.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Comment::query()->with('post');

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {

                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {

            $query->where('status', $request->status);
        }

        /*
        |--------------------------------------------------------------------------
        | Post Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('post_id')) {

            $query->where('post_id', $request->post_id);
        }

        $comments = $query
            ->latest()
            ->paginate(
                AdminPageSize::resolve($request, $query, 15)
            );

        return response()->json([

            'success' => true,

            'message' => 'Comments retrieved successfully.',

            'data' => CommentResource::collection(
                $comments->items()
            ),

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
     * Comment dashboard statistics.
     */
    public function statistics(): JsonResponse
    {
        $statistics = Cache::remember(

            CacheService::commentStatisticsKey(),

            now()->addMinutes(30),

            function () {

                $monthlyGrowth = Comment::query()
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('COUNT(*) as total')
                    )
                    ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get()
                    ->map(function ($item) {

                        return [
                            'month' => Carbon::create(
                                $item->year,
                                $item->month,
                                1
                            )->format('M Y'),

                            'total' => (int) $item->total,
                        ];
                    });

                return [

                    'total_comments' => Comment::count(),

                    'pending_comments' => Comment::where('status', 'pending')->count(),

                    'approved_comments' => Comment::where('status', 'approved')->count(),

                    'spam_comments' => Comment::where('status', 'spam')->count(),

                    'rejected_comments' => Comment::where('status', 'rejected')->count(),

                    'today_comments' => Comment::whereDate(
                        'created_at',
                        today()
                    )->count(),

                    'this_month_comments' => Comment::whereYear(
                        'created_at',
                        now()->year
                    )->whereMonth(
                        'created_at',
                        now()->month
                    )->count(),

                    'monthly_growth' => $monthlyGrowth,

                ];
            }
        );

        return ApiResponse::success(
            $statistics,
            'Comment statistics retrieved successfully.'
        );
    }

    /**
     * Show comment.
     */
    public function show(Comment $comment): JsonResponse
    {
        $comment->load('post', 'parent');

        return ApiResponse::success(
            CommentResource::make($comment),
            'Comment retrieved successfully.'
        );
    }

    /**
     * Approve comment.
     */
    public function approve(Comment $comment): JsonResponse
    {
        $comment->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        CacheService::clearComments($comment->post->slug);
        CacheService::clearCommentStatistics();

        return ApiResponse::success(
            CommentResource::make($comment),
            'Comment approved successfully.'
        );
    }

    /**
     * Mark spam.
     */
    public function spam(Comment $comment): JsonResponse
    {
        $comment->update([
            'status' => 'spam',
        ]);

        CacheService::clearComments($comment->post->slug);
        CacheService::clearCommentStatistics();

        return ApiResponse::success(
            CommentResource::make($comment),
            'Comment marked as spam.'
        );
    }

    /**
     * Reject comment.
     */
    public function reject(Comment $comment): JsonResponse
    {
        $comment->update([
            'status' => 'rejected',
        ]);

        CacheService::clearComments($comment->post->slug);
        CacheService::clearCommentStatistics();

        return ApiResponse::success(
            CommentResource::make($comment),
            'Comment rejected successfully.'
        );
    }

    /**
     * Delete comment.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $slug = $comment->post->slug;

        $comment->delete();

        CacheService::clearComments($slug);
        CacheService::clearCommentStatistics();
        
        return ApiResponse::success(
            [],
            'Comment deleted successfully.'
        );
    }
}