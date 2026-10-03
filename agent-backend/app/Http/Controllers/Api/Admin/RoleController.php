<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::query()->withCount('users')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name');
        $roles = $query->paginate(AdminPageSize::resolve($request, $query, 15));

        return RoleResource::collection($roles)
            ->additional(['success' => true, 'message' => 'Roles retrieved successfully.']);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create($request->validated());

        return ApiResponse::success(new RoleResource($role->loadCount('users')), 'Role created successfully.', 201);
    }

    public function show(Role $role): JsonResponse
    {
        return ApiResponse::success(new RoleResource($role->loadCount('users')), 'Role retrieved successfully.');
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();

        if ($role->slug === 'admin' && ($validated['slug'] !== 'admin' || ! $validated['status'])) {
            return ApiResponse::error('The administrator role slug and status are protected.', null, 422);
        }

        $role->update($validated);

        return ApiResponse::success(new RoleResource($role->refresh()->loadCount('users')), 'Role updated successfully.');
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->slug === 'admin') {
            return ApiResponse::error('The administrator role cannot be deleted.', null, 422);
        }

        if ($role->users()->exists()) {
            return ApiResponse::error('Reassign users before deleting this role.', null, 422);
        }

        $role->delete();

        return ApiResponse::success(null, 'Role deleted successfully.');
    }
}
