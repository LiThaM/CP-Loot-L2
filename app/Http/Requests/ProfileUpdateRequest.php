<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // `name`/`email` are `sometimes` because this same endpoint also
        // receives the standalone *Preferences* form (theme/language/email
        // opt-in), which doesn't carry the identity fields. Without
        // `sometimes` a member saving e.g. dark mode got "the name field is
        // required". When the fields ARE present (profile-info form) they're
        // still required and validated.
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'theme_preference'    => ['sometimes', 'string', 'in:light,dark,system'],
            'language_preference' => ['sometimes', 'string', 'in:es,en,system'],
            'changelog_emails_enabled' => ['sometimes', 'boolean'],
            // Optional details for the main character — see migration
            // 2026_05_22_000019_add_main_char_fields_to_users.
            'main_class_id' => ['nullable', 'integer', 'exists:l2_classes,id'],
            'main_race'     => ['nullable', 'string', 'max:20'],
            'main_level'    => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
