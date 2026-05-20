<?php

namespace App\Contexts\Telemetry\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadDigitTemplatesRequest extends FormRequest
{
    public const MAX_ZIP_BYTES = 1_048_576;     // 1 MB
    public const MAX_PNG_ENTRIES = 200;
    public const MAX_UNCOMPRESSED_BYTES = 5_242_880; // 5 MB total
    public const PER_ANON_TOKEN_QUOTA = 50;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'templates' => [
                'required',
                'file',
                'max:' . (int) (self::MAX_ZIP_BYTES / 1024),
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'templates.required' => 'Multipart file "templates" is required (ZIP).',
            'templates.max' => 'ZIP exceeds 1 MB.',
            'templates.mimetypes' => 'Only ZIP files are accepted.',
        ];
    }
}
