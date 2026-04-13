<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1', 'max:40'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:8000'],
            'client_context' => ['sometimes', 'array'],
            'client_context.location' => ['sometimes', 'array'],
            'client_context.location.latitude' => ['required_with:client_context.location', 'numeric', 'between:-90,90'],
            'client_context.location.longitude' => ['required_with:client_context.location', 'numeric', 'between:-180,180'],
            'client_context.location.accuracy' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500000'],
        ];
    }
}
