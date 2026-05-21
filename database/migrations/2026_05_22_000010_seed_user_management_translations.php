<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'system.users.admin_locked'    => ['es' => 'solo admins',                                                    'en' => 'admins only'],
        'system.users.cannot_self_edit' => ['es' => 'No puedes cambiar tu propio rol. Pide a otro admin que lo haga.', 'en' => 'You cannot change your own role. Ask another admin to do it.'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $exists = DB::table('translations')->where('key', $key)->where('language', $lang)->exists();
                if (!$exists) {
                    $rows[] = ['language' => $lang, 'key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        if (!empty($rows)) {
            DB::table('translations')->insert($rows);
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
