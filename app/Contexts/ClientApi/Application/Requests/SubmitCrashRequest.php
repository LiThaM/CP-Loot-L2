<?php

namespace App\Contexts\ClientApi\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCrashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bot_version' => ['required', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'python_version' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:1000'],
            'stack_trace' => ['required', 'string', 'max:20000'],
            'context' => ['nullable', 'array'],
        ];
    }
}
