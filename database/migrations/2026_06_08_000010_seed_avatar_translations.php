<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Avatar upload
        'profile.avatar.upload_hint' => ['es' => 'Subir foto (max 3MB)', 'en' => 'Upload photo (max 3MB)'],
        'profile.avatar.saved' => ['es' => 'Avatar actualizado', 'en' => 'Avatar updated'],
        'profile.avatar.error' => ['es' => 'No se pudo subir el avatar', 'en' => 'Could not upload avatar'],

        // Profile hero
        'profile.hero.kicker' => ['es' => 'Tu perfil', 'en' => 'Your profile'],
        'profile.hero.subtitle' => [
            'es' => 'Gestiona tu cuenta, contraseña, preferencias y avatar desde aquí.',
            'en' => 'Manage your account, password, preferences and avatar from here.',
        ],
        'profile.hero.verified' => ['es' => 'Email verificado', 'en' => 'Email verified'],

        // Role labels (used in the hero badge)
        'role.admin' => ['es' => 'Admin', 'en' => 'Admin'],
        'role.cp_leader' => ['es' => 'CP Leader', 'en' => 'CP Leader'],
        'role.accountant' => ['es' => 'Tesorero', 'en' => 'Accountant'],
        'role.member' => ['es' => 'Miembro', 'en' => 'Member'],
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
