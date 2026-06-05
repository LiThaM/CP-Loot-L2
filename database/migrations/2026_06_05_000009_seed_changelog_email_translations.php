<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'profile.preferences.changelog_emails.label' => [
            'es' => 'Recibir avisos de nuevas features por email',
            'en' => 'Receive new feature alerts by email',
        ],
        'profile.preferences.changelog_emails.help' => [
            'es' => 'Solo se envían si eres CP leader. Puedes desactivarlo en cualquier momento.',
            'en' => 'Only sent if you are a CP leader. You can turn this off at any time.',
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
