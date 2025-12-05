<?php

namespace App\Http\Requests;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agent = $this->route('agent');

        // Can only update own agents (not system agents or other users')
        return $agent instanceof Agent
            && $agent->user_id !== null
            && $agent->user_id === $this->user()->id;
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
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
