<?php

namespace App\Contexts\ClientApi\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitVersionDownloadedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_version' => ['nullable', 'string', 'max:50'],
            'to_version' => ['required', 'string', 'max:50'],
        ];
    }
}
