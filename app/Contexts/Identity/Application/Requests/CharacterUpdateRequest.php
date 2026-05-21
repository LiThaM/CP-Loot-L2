<?php

namespace App\Contexts\Identity\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CharacterUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller already verifies ownership before binding the
        // route model; this just guards the basic auth case.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        $characterId = $this->route('character')?->id;
        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('characters', 'name')
                    ->where('user_id', $userId)
                    ->ignore($characterId),
            ],
            'l2_class_id' => ['nullable', 'integer', 'exists:l2_classes,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
