<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;

use App\Http\Requests\SubscribeNewsletterRequest;

use App\Http\Resources\Api\NewsletterSubscriberResource;

use App\Models\NewsletterSubscriber;

use App\Support\ApiResponse;
use App\Http\Requests\UnsubscribeNewsletterRequest;
use App\Services\CacheService;

class NewsletterController extends Controller
{
    /**
     * Subscribe to newsletter.
     */
    public function subscribe(
        SubscribeNewsletterRequest $request
    ): JsonResponse {

        $subscriber = NewsletterSubscriber::where(
            'email',
            strtolower($request->email)
        )->first();

        /*
        |--------------------------------------------------------------------------
        | Already Active Subscriber
        |--------------------------------------------------------------------------
        */

        if ($subscriber && $subscriber->status) {

            return ApiResponse::success(

                NewsletterSubscriberResource::make($subscriber),

                'You are already subscribed.'

            );
        }

        /*
        |--------------------------------------------------------------------------
        | Re-subscribe Existing User
        |--------------------------------------------------------------------------
        */

        if ($subscriber && ! $subscriber->status) {

            $subscriber->update([

                'status' => true,

                'name' => $request->name ?? $subscriber->name,

                'subscribed_at' => now(),

                'unsubscribed_at' => null,

            ]);

            return ApiResponse::success(

                NewsletterSubscriberResource::make($subscriber),

                'Subscription restored successfully.'

            );
        }

        /*
        |--------------------------------------------------------------------------
        | New Subscriber
        |--------------------------------------------------------------------------
        */

        $subscriber = NewsletterSubscriber::create([

            'email' => strtolower($request->email),

            'name' => $request->name,

            'status' => true,

            'subscribed_at' => now(),

        ]);

        CacheService::clearNewsletterCaches();

        return ApiResponse::success(

            NewsletterSubscriberResource::make($subscriber),

            'Subscribed successfully.',

            201

        );
    }

    /**
     * Unsubscribe from newsletter.
     */
    public function unsubscribe(
        UnsubscribeNewsletterRequest $request
    ): JsonResponse {

        $subscriber = NewsletterSubscriber::where(
            'email',
            strtolower($request->email)
        )->first();

        /*
        |--------------------------------------------------------------------------
        | Subscriber Not Found
        |--------------------------------------------------------------------------
        */

        if (! $subscriber) {

            return ApiResponse::error(
                'Subscriber not found.',
                null, 404
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Already Unsubscribed
        |--------------------------------------------------------------------------
        */

        if (! $subscriber->status) {

            return ApiResponse::success(
                NewsletterSubscriberResource::make($subscriber),
                'You are already unsubscribed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Unsubscribe
        |--------------------------------------------------------------------------
        */

        $subscriber->update([

            'status' => false,

            'unsubscribed_at' => now(),

        ]);
        

        CacheService::clearNewsletterCaches();

        return ApiResponse::success(
            NewsletterSubscriberResource::make($subscriber),
            'Unsubscribed successfully.'
        );
    }

}