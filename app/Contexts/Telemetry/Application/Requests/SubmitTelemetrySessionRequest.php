<?php

namespace App\Contexts\Telemetry\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTelemetrySessionRequest extends FormRequest
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
            'session_duration_seconds' => ['required', 'integer', 'min:0', 'max:604800'],
            'char_class' => ['nullable', 'string', 'max:50'],
            'char_level' => ['nullable', 'integer', 'min:1', 'max:200'],
            'xp_per_hour' => ['nullable', 'integer', 'min:0'],
            'adena_per_hour' => ['nullable', 'integer', 'min:0'],
            'ss_per_hour' => ['nullable', 'integer', 'min:0'],
            'deaths' => ['nullable', 'integer', 'min:0'],
            'level_ups' => ['nullable', 'integer', 'min:0'],
            'top_items' => ['nullable', 'array', 'max:20'],
            'top_items.*' => ['string', 'max:80'],
            'ocr_engine' => ['nullable', 'string', 'max:50'],
            'ocr_avg_ms' => ['nullable', 'numeric', 'min:0'],
            'ocr_p95_ms' => ['nullable', 'numeric', 'min:0'],
            'ocr_errors' => ['nullable', 'integer', 'min:0'],
            'ocr_gpu_used' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        // Reject explicit char_name to defend against accidental leakage.
        $validator->after(function ($v) {
            foreach (['char_name', 'character', 'nickname', 'name'] as $forbidden) {
                if ($this->has($forbidden)) {
                    $v->errors()->add($forbidden, 'Field is not accepted in telemetry (PII).');
                }
            }
        });
    }
}
