<?php

namespace App\Contexts\ClientApi\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAppCrashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_version' => ['required', 'string', 'max:50'],
            'char' => ['nullable', 'string', 'max:100'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'traceback' => ['required', 'string', 'max:50000'],
            'context' => ['nullable', 'array'],
            'ts' => ['nullable'],
        ];
    }
}
