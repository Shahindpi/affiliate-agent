<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => Str::slug($this->string('title')->value())]);
        }
    }

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
        return [
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:280',
                'unique:posts,slug',
            ],

            'excerpt' => [
                'nullable',
                'string',
            ],

            'content' => [
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
