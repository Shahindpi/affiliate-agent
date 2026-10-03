<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Support\ApiResponse;

class UploadController extends Controller
{
    /**
     * Upload an image.
     */
    public function image(Request $request)
    {
        $validated = $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2 MB
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Store Image
        |--------------------------------------------------------------------------
        */

        $path = $validated['image']->store(
            'uploads/images',
            'public'
        );

        return ApiResponse::success([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], 'Image uploaded successfully.');
    }
}