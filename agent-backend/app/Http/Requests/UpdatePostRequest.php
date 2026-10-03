<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $postId = $this->route('post')?->id;

        return [
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:280',
                'unique:posts,slug,' . $postId,
            ],

            'excerpt' => [
                'nullable',
                'string',
            ],

            'content' => [
                'sometimes',
                'required',
                'string',
            ],

            'featured_image' => [
                'nullable',
                'string',
                'max:255',
            ],

            'post_type' => [
                'nullable',
                'string',
                'max:30',
            ],

            'status' => [
                'nullable',
                'string',
                'max:30',
            ],

            'published_at' => [
                'nullable',
                'date',
            ],

            'reading_time' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'allow_comments' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}