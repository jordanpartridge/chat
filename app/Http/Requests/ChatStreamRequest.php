<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Note: ai_model_id is nullable here because existing chats have a default model.
     * The controller falls back to the chat's ai_model_id when not provided.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:10000'],
            'ai_model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
        ];
    }
}
