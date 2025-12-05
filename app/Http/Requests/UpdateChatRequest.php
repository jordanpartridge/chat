<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Chat;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $chat = $this->route('chat');

        return $chat instanceof Chat && $chat->user_id === $this->user()->id;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ai_model_id' => ['sometimes', 'integer', 'exists:ai_models,id'],
            'title' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
