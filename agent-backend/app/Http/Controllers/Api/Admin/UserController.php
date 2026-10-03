<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->with('role')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->value());
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->boolean('status')))
            ->latest();
        $users = $query->paginate(AdminPageSize::resolve($request, $query, 15));

        return UserResource::collection($users)->additional([
            'success' => true,
            'message' => 'Users retrieved successfully.',
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $role = Role::query()->whereKey($request->integer('role_id'))->where('status', true)->firstOrFail();
        $validated = $request->validated();
        $validated['role_id'] = $role->id;

        $user = User::create($validated)->load('role');

        return ApiResponse::success(new UserResource($user), 'User created successfully.', 201);
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user->load('role')), 'User retrieved successfully.');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::query()->whereKey($validated['role_id'])->where('status', true)->firstOrFail();

        if ($request->user()->is($user)) {
            if (! $request->boolean('status')) {
                return ApiResponse::error('You cannot deactivate your own account.', null, 422);
            }

            if ((int) $validated['role_id'] !== (int) $user->role_id) {
                return ApiResponse::error('You cannot change your own role.', null, 422);
            }
        }

        $removesActiveAdmin = $user->status
            && $user->role?->slug === 'admin'
            && (! $validated['status'] || $role->slug !== 'admin');

        if ($removesActiveAdmin) {
            $activeAdmins = User::query()
                ->where('status', true)
                ->whereHas('role', fn ($query) => $query->where('slug', 'admin')->where('status', true))
                ->count();

            if ($activeAdmins <= 1) {
                return ApiResponse::error('The last active administrator cannot be deactivated or reassigned.', null, 422);
            }
        }

        $validated['role_id'] = $role->id;
        $user->update($validated);

        if (! $user->status) {
            $user->tokens()->delete();
        }

        return ApiResponse::success(new UserResource($user->refresh()->load('role')), 'User updated successfully.');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->delete();

        return ApiResponse::success(null, 'User password changed successfully.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return ApiResponse::error('You cannot delete your own account.', null, 422);
        }

        if ($user->role?->slug === 'admin') {
            $activeAdmins = User::query()
                ->where('status', true)
                ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
                ->count();

            if ($activeAdmins <= 1) {
                return ApiResponse::error('The last active administrator cannot be deleted.', null, 422);
            }
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return ApiResponse::success(null, 'User deleted successfully.');
    }
}
