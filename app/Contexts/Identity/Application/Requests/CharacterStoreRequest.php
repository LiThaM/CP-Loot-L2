<?php

namespace App\Contexts\Identity\Application\Requests;

use App\Contexts\Identity\Domain\Models\L2Class;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CharacterStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('characters', 'name')->where('user_id', $userId),
            ],
            'l2_class_id' => ['nullable', 'integer', 'exists:l2_classes,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Race comes from the chosen class — we just sanity-check.
            // Coherence between race and class is enforced by the Character
            // model's saving hook (race is always derived from the class).
        });
    }
}
