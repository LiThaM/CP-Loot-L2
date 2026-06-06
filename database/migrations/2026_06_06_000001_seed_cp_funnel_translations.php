<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'welcome.modal.cp_request.account_section' => [
            'es' => 'Tu cuenta',
            'en' => 'Your account',
        ],
        'welcome.modal.cp_request.account_name' => [
            'es' => 'Tu nombre',
            'en' => 'Your name',
        ],
        'welcome.modal.cp_request.password' => [
            'es' => 'Contraseña',
            'en' => 'Password',
        ],
        'welcome.modal.cp_request.password_confirmation' => [
            'es' => 'Repite la contraseña',
            'en' => 'Confirm password',
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                DB::table('translations')->updateOrInsert(
                    ['language' => $lang, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
