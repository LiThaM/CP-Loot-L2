<?php

namespace App\Contexts\Telemetry\Application\Requests;

use App\Contexts\Telemetry\Domain\Models\OcrSample;
use Illuminate\Foundation\Http\FormRequest;

class SubmitOcrSampleRequest extends FormRequest
{
    public const MAX_PNG_BYTES = 204_800; // 200 KB

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'mimes:png',
                'max:' . (int) (self::MAX_PNG_BYTES / 1024),
            ],
            'ground_truth' => ['required', 'string', 'max:100'],
            'category' => ['required', 'string', 'in:' . implode(',', OcrSample::CATEGORIES)],
            'expected_value' => ['nullable', 'string', 'max:100'],
            'actual_ocr' => ['nullable', 'string', 'max:100'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $gt = (string) $this->input('ground_truth', '');
            // Anti-chat-with-nick defense: reject "<name>: <msg>" patterns.
            if (preg_match('/^[^:\s]{2,20}:\s/u', $gt)) {
                $v->errors()->add(
                    'ground_truth',
                    'ground_truth looks like a chat line with a player name and is rejected for privacy reasons.'
                );
            }
        });
    }
}
