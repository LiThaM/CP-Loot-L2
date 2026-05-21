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
            // Old field name (kept for backward-compat with the running client)
            // and new spec field (`ocr_text`). At least one must be present —
            // see withValidator below.
            'ground_truth' => ['nullable', 'string', 'max:500'],
            'ocr_text' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'string', 'in:' . implode(',', OcrSample::CATEGORIES)],
            'expected_value' => ['nullable', 'string', 'max:500'],
            'actual_ocr' => ['nullable', 'string', 'max:500'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'bot_version' => ['nullable', 'string', 'max:50', 'regex:/^[\w.\-+]+$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $gt = (string) $this->input('ground_truth', '');
            $ocrText = (string) $this->input('ocr_text', '');

            if ($gt === '' && $ocrText === '') {
                $v->errors()->add('ocr_text', 'Either ocr_text or ground_truth is required.');
                return;
            }

            // Anti-chat-with-nick defense: reject "<name>: <msg>" patterns on
            // either text field. Party nicks are PII; we must not corpus them.
            foreach (['ground_truth' => $gt, 'ocr_text' => $ocrText] as $field => $text) {
                if ($text !== '' && preg_match('/^[^:\s]{2,20}:\s/u', $text)) {
                    $v->errors()->add(
                        $field,
                        $field.' looks like a chat line with a player name and is rejected for privacy reasons.'
                    );
                }
            }
        });
    }
}
