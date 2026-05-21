<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Corrección de seguridad** en la gestión de usuarios:

- Solo los **administradores globales** pueden asignar el rol `admin`. Antes un líder fundador de CP podía promocionarse a sí mismo (o a un miembro) a admin global desde `/system/users`. Ya no.
- **Nadie puede cambiar su propio rol** — ni siquiera un admin. Para ascender o degradar a un admin se necesita otro admin.
- Los líderes fundadores siguen pudiendo gestionar roles dentro de su CP (cp_leader / accountant / member) como hasta ahora.

Si necesitas degradar a un usuario que se hubiera escalado: `php artisan users:list-orphan-admins` lista los candidatos sin tocarlos; `--fix` los pasa a `cp_leader` tras confirmación.
MD;

        $bodyEn = <<<'MD'
**Security fix** in user management:

- Only **global admins** can now assign the `admin` role. Previously a founder CP leader could promote themselves (or a member) to global admin from `/system/users`. Patched.
- **Nobody can change their own role** — not even an admin. Promoting or demoting an admin requires another admin.
- Founder leaders can still manage roles within their CP (cp_leader / accountant / member) as before.

To demote any user who escalated: `php artisan users:list-orphan-admins` lists candidates read-only; `--fix` demotes them to `cp_leader` after confirmation.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'security', 'version' => null, 'title_en' => 'Security: privilege-escalation fix in user management'],
            [
                'audience' => 'web',
                'title_es' => 'Seguridad: corrección de escalada de privilegios',
                'title_en' => 'Security: privilege-escalation fix in user management',
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'Security: privilege-escalation fix in user management')
            ->delete();
    }
};
