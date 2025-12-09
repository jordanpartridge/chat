<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
            'ai_model_id' => ['required', 'integer', 'exists:ai_models,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ];
    }
}
