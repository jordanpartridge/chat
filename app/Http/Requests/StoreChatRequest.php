<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ModelName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'model' => ['required', 'string', Rule::enum(ModelName::class)],
        ];
    }
}
