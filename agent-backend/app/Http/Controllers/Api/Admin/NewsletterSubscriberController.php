<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

use App\Services\CacheService;

use App\Models\NewsletterSubscriber;

use App\Http\Requests\UpdateSubscriberStatusRequest;

use App\Http\Resources\Api\NewsletterSubscriberResource;

use App\Support\ApiResponse;

class NewsletterSubscriberController extends Controller
{
    /**
     * Subscriber listing.
     */
    public function index(Request $request): JsonResponse
    {
        $query = NewsletterSubscriber::query();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $query->where(
                'status',
                filter_var($request->status, FILTER_VALIDATE_BOOLEAN)
            );
        }

        $subscribers = $query
            ->latest()
            ->paginate(
                AdminPageSize::resolve($request, $query, 15)
            );

        return response()->json([
            'success' => true,
            'message' => 'Subscribers retrieved successfully.',
            'data' => NewsletterSubscriberResource::collection(
                $subscribers->items()
            ),
            'links' => [
                'first' => $subscribers->url(1),
                'last' => $subscribers->url($subscribers->lastPage()),
                'prev' => $subscribers->previousPageUrl(),
                'next' => $subscribers->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $subscribers->currentPage(),
                'from' => $subscribers->firstItem(),
                'last_page' => $subscribers->lastPage(),
                'path' => $subscribers->path(),
                'per_page' => $subscribers->perPage(),
                'to' => $subscribers->lastItem(),
                'total' => $subscribers->total(),
            ],
        ]);
    }

    /**
     * Show subscriber.
     */
    public function show(
        NewsletterSubscriber $subscriber
    ): JsonResponse {

        return ApiResponse::success(
            NewsletterSubscriberResource::make($subscriber),
            'Subscriber retrieved successfully.'
        );
    }

    /**
     * Update subscriber status.
     */
    public function updateStatus(
        UpdateSubscriberStatusRequest $request,
        NewsletterSubscriber $subscriber
    ): JsonResponse {

        $status = $request->boolean('status');

        $subscriber->update([
            'status' => $status,
            'subscribed_at' => $status
                ? now()
                : $subscriber->subscribed_at,
            'unsubscribed_at' => $status
                ? null
                : now(),
        ]);

        CacheService::clearNewsletterCaches();

        return ApiResponse::success(
            NewsletterSubscriberResource::make($subscriber),
            'Subscriber status updated successfully.'
        );
    }

    /**
     * Delete subscriber permanently.
     */
    public function destroy(
        NewsletterSubscriber $subscriber
    ): JsonResponse {

        $subscriber->delete();

        CacheService::clearNewsletterCaches();

        return ApiResponse::success(
            [],
            'Subscriber deleted successfully.'
        );
    }

    /**
     * Newsletter statistics.
     */
    public function statistics(): JsonResponse
    {
        $statistics = Cache::remember(
            CacheService::newsletterStatisticsKey(),
            now()->addHours(2),
            function () {

                $overview = [

                    'total_subscribers' =>
                        NewsletterSubscriber::count(),

                    'active_subscribers' =>
                        NewsletterSubscriber::active()->count(),

                    'inactive_subscribers' =>
                        NewsletterSubscriber::inactive()->count(),

                    'new_today' =>
                        NewsletterSubscriber::whereDate(
                            'subscribed_at',
                            today()
                        )->count(),

                    'new_this_month' =>
                        NewsletterSubscriber::whereMonth(
                            'subscribed_at',
                            now()->month
                        )
                        ->whereYear(
                            'subscribed_at',
                            now()->year
                        )
                        ->count(),
                ];

                /*
                |--------------------------------------------------------------------------
                | Monthly Growth (Last 12 Months)
                |--------------------------------------------------------------------------
                */

                $monthlyGrowth = collect();

                for ($i = 11; $i >= 0; $i--) {

                    $date = now()->subMonths($i);

                    $monthlyGrowth->push([

                        'month' => $date->format('M'),

                        'year' => $date->year,

                        'subscribers' => NewsletterSubscriber::whereYear(
                            'subscribed_at',
                            $date->year
                        )
                        ->whereMonth(
                            'subscribed_at',
                            $date->month
                        )
                        ->count(),
                    ]);
                }

                return [

                    'overview' => $overview,

                    'monthly_growth' => $monthlyGrowth,
                ];
            }
        );

        return ApiResponse::success(
            $statistics,
            'Subscriber statistics retrieved successfully.'
        );
    }
}