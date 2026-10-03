<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'site_name' => 'required|string|max:150',
            'site_tagline' => 'nullable|string|max:255',
            'site_url' => 'nullable|url',

            'default_meta_title' => 'nullable|string|max:255',
            'default_meta_description' => 'nullable|string|max:500',
            'default_meta_keywords' => 'nullable|string|max:500',

            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:30',
            'contact_address' => 'nullable|string|max:500',

            'facebook' => 'nullable|url',
            'twitter' => 'nullable|url',
            'linkedin' => 'nullable|url',
            'github' => 'nullable|url',
            'youtube' => 'nullable|url',
            'instagram' => 'nullable|url',

            'google_analytics_id' => ['nullable', 'regex:/^G-[A-Z0-9]+$/', 'max:50'],
            'google_search_console_id' => 'nullable|string|max:100',

        ];
    }
}