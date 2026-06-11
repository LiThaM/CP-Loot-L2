<?php

namespace App\Contexts\ClientApi\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitGameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'char' => ['required', 'string', 'max:100'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'xp' => ['nullable', 'integer', 'min:0'],
            'sp' => ['nullable', 'integer', 'min:0'],
            'adena' => ['nullable', 'integer', 'min:0'],
            'mobs_killed' => ['nullable', 'integer', 'min:0'],
            'deaths' => ['nullable', 'integer', 'min:0'],
            'level_ups' => ['nullable', 'integer', 'min:0'],
            'xp_per_hour' => ['nullable', 'numeric', 'min:0'],
            'adena_per_hour' => ['nullable', 'numeric', 'min:0'],
            'items_summary' => ['nullable', 'array', 'max:50'],
            'items_summary.*.name' => ['required', 'string', 'max:200'],
            'items_summary.*.count' => ['required', 'integer', 'min:0'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
