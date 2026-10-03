<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Http\JsonResponse;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Http\Requests\UploadAvatarRequest;


class ProfileController extends Controller
{

    /**
     * Upload or replace user avatar.
     */
    public function uploadAvatar(
        UploadAvatarRequest $request
    ): JsonResponse {

        $user = auth()->user();

        /*
        |--------------------------------------------------------------------------
        | Delete old avatar
        |--------------------------------------------------------------------------
        */

        if (
            $user->avatar &&
            Storage::disk('public')->exists($user->avatar)
        ) {
            Storage::disk('public')->delete($user->avatar);
        }

        /*
        |--------------------------------------------------------------------------
        | Upload new avatar
        |--------------------------------------------------------------------------
        */

        $file = $request->file('avatar');

        $path = $file->storeAs(
            'uploads/avatars',
            Str::uuid() . '.' . $file->extension(),
            'public'
        );

        $user->update([
            'avatar' => $path,
        ]);

        return ApiResponse::success(
            UserResource::make(
                $user->refresh()->load('role')
            ),
            'Avatar uploaded successfully.'
        );
    }

    /**
     * Get authenticated admin profile.
     */
    public function show(): JsonResponse
    {
        $user = auth()->user()->load('role');

        return ApiResponse::success(
            UserResource::make($user),
            'Profile retrieved successfully.'
        );
    }

    /**
     * Update authenticated admin profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();

        $user->update([
            'name' => trim($request->name),
            'username' => $request->username,
            'phone' => $request->phone,
        ]);

        return ApiResponse::success(
            UserResource::make(
                $user->refresh()->load('role')
            ),
            'Profile updated successfully.'
        );
    }

    /**
     * Change authenticated user's password.
     */
    public function changePassword(
        ChangePasswordRequest $request
    ): JsonResponse {

        $user = auth()->user();

        if (! Hash::check(
            $request->current_password,
            $user->password
        )) {

            return ApiResponse::error(
                'Current password is incorrect.',
                null, 422
            );
        }

        $user->update([
            'password' => $request->password,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Logout all other devices
        |--------------------------------------------------------------------------
        */

        $currentToken = $user->currentAccessToken();

        if ($currentToken && method_exists($currentToken, 'getKey')) {
            // Authenticated using a Personal Access Token.
            $user->tokens()
                ->whereKeyNot($currentToken->getKey())
                ->delete();
        }

        return ApiResponse::success(
            [],
            'Password changed successfully.'
        );
    }
}