<?php

namespace App\Contexts\ClientApi\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBotTicketRequest extends FormRequest
{
    public const MAX_BOT_CONTEXT_BYTES = 2_097_152; // 2 MB

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'in:ocr_error,crash,ui_bug,feature_request,other'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'bot_context' => [
                'nullable',
                'file',
                'max:' . (int) (self::MAX_BOT_CONTEXT_BYTES / 1024),
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
            ],
        ];
    }
}
