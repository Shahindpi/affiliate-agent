<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPageSize;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Services\CacheService;
use App\Support\ApiResponse;
use App\Http\Resources\Api\CategoryResource;


class CategoryController extends Controller
{
    /**
     * List categories.
     */
    public function index(Request $request)
    {
        $query = Category::query()
            ->withCount('posts')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search');

            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->paginate(AdminPageSize::resolve($request, $query, 20));

        return ApiResponse::paginated(
            CategoryResource::collection($categories),
            'Categories retrieved successfully.'
        );
    }


    /**
     * Create category.
     */
    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        $validated['slug'] =
            $validated['slug']
            ?? Str::slug($validated['name']);

        $category = Category::create($validated);
        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();


        return ApiResponse::success(
            new CategoryResource($category),
            'Category created successfully.',
            201
        );
    }


    /**
     * Show category.
     */
    public function show(Category $category)
    {
        $category->loadCount('posts');

        return ApiResponse::success(
            new CategoryResource($category),
            'Category retrieved successfully.'
        );
    }


    /**
     * Update category.
     */
    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ) {

        /*
        |--------------------------------------------------------------------------
        | Store Previous Slug
        |--------------------------------------------------------------------------
        */

        $oldSlug = $category->slug;

        /*
        |--------------------------------------------------------------------------
        | Validate Request
        |--------------------------------------------------------------------------
        */

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Generate Slug From Name (If Not Provided)
        |--------------------------------------------------------------------------
        */

        if (
            isset($validated['name']) &&
            !isset($validated['slug'])
        ) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        /*
        |--------------------------------------------------------------------------
        | Update Category
        |--------------------------------------------------------------------------
        */

        $category->update($validated);

        /*
        |--------------------------------------------------------------------------
        | Refresh Model
        |--------------------------------------------------------------------------
        */

        $category->refresh();

        /*
        |--------------------------------------------------------------------------
        | Clear Cache
        |--------------------------------------------------------------------------
        */

        // Clear old slug cache (if slug changed)
        CacheService::clearCategory($oldSlug);

        // Clear current slug cache
        CacheService::clearCategory($category->slug);
        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            new CategoryResource(
                $category->fresh()->loadCount('posts')
            ),
            'Category updated successfully.'
        );
    }


    public function destroy(Category $category): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Protect categories that still contain posts
        |--------------------------------------------------------------------------
        */

        if ($category->posts()->exists() || $category->affiliateProducts()->exists() || $category->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Reassign posts, products and child categories before deleting this category.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Clear Cache Before Delete
        |--------------------------------------------------------------------------
        */

        CacheService::clearCategory($category->slug);

        $category->delete();
        CacheService::clearPublicCaches();
        CacheService::clearDashboardCaches();


        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}
