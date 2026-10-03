<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::error(
                'Invalid credentials.',
                null, 401
            );
        }

        if (! $user->status) {

            return ApiResponse::error(
                'Your account is inactive.',
                null, 403
            );
        }

        // Remove previous tokens (optional but recommended)
        $user->tokens()->delete();

        $token = $user->createToken('dewdora-admin')->plainTextToken;

        return ApiResponse::success(
            [
                'token' => $token,
                'user' => new UserResource(
                    $user->load('role')
                ),
            ],
            'Login successful.'
        );
    }

    /**
     * Authenticated user.
     */
    public function me(): JsonResponse
    {
        $user = auth()->user()->load('role');

        return ApiResponse::success(
            UserResource::make($user),
            'Authenticated user retrieved successfully.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
