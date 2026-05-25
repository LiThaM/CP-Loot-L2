<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'landing.nav.web_changelog'         => ['es' => 'Cambios web',                                    'en' => 'Web changes'],
        'landing.web_changelog.title'       => ['es' => 'Cambios en la web',                              'en' => "What's new in the web app"],
        'landing.web_changelog.subtitle'    => ['es' => 'Features y fixes desplegados en adenaledger.com (no en la app de escritorio).', 'en' => 'Features and fixes shipped to adenaledger.com (separate from the desktop app).'],
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
