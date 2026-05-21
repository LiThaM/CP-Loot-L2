<?php

namespace App\Contexts\Loot\Application\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CraftBulkPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        if (!$u || !$u->cp_id) {
            return false;
        }
        $role = $u->role?->name;
        return in_array($role, ['admin', 'cp_leader', 'accountant'], true);
    }

    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1', 'max:50'],
            'orders.*.recipe_id' => ['required', 'integer', 'exists:recipes,id'],
            'orders.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
