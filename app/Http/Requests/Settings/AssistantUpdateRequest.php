<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssistantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $model = $this->input('ai_chat_model');
        if ($model === '' || (is_string($model) && trim($model) === '')) {
            $this->merge(['ai_chat_model' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ai_chat_provider' => ['nullable', 'string', Rule::in(['openai', 'groq'])],
            'ai_chat_model' => ['nullable', 'string', 'max:128'],
        ];
    }
}
