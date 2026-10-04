<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

class RevisionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->role?->slug === 'admin'; }
    public function rules(): array
    {
        return ['expected_version_id' => 'required|integer', 'feedback' => 'required|string|max:6000', 'target' => 'sometimes|string|max:100', 'patch' => 'sometimes|array:script,scenes,captions,cta,disclosure,metadata,voice'];
    }
}
