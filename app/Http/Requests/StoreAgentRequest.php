<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'system_prompt' => ['nullable', 'string'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'default_model_id' => ['nullable', 'exists:ai_models,id'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string'],
            'capabilities' => ['nullable', 'array'],
        ];
    }
}
