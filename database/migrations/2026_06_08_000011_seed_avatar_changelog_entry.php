<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Por fin puedes subir tu propio **avatar**. Ve a [/profile](/profile), pasa el ratón sobre la imagen y haz click — se admite PNG/JPG hasta 3MB. El servidor reescala automáticamente a 512×512px y guarda como JPG optimizado.

De paso rediseñamos toda la pantalla de perfil:
- **Hero nuevo** con tu avatar, nombre, rol, CP y email verificado a la vista.
- **Atajos rápidos** a /profile/stats, /characters, /warehouse y /tickets — el resto de la sección "Yo".
- **Secciones con iconos** y mejor jerarquía visual: información personal, contraseña, preferencias y zona peligrosa.

Tu avatar aparece automáticamente en la navbar, el listado de miembros del CP, el roster admin, las stats personales y donde antes salía solo la inicial.
MD;

        $bodyEn = <<<'MD'
You can finally upload your own **avatar**. Head to [/profile](/profile), hover the picture and click — PNG/JPG up to 3MB accepted. The server auto-rescales to 512×512px and stores it as an optimized JPG.

We also redesigned the whole profile screen:
- **New hero** showing your avatar, name, role, CP and email-verified badge upfront.
- **Quick links** to /profile/stats, /characters, /warehouse and /tickets — the rest of the "Me" section.
- **Sections with icons** and better visual hierarchy: personal info, password, preferences and danger zone.

Your avatar shows up automatically in the navbar, the CP member list, the admin roster, your personal stats and anywhere the initial letter used to be.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'User avatars + profile screen redesign'],
            [
                'audience' => 'web',
                'title_es' => 'Avatares de usuario + rediseño del perfil',
                'title_en' => 'User avatars + profile screen redesign',
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
            ->where('title_en', 'User avatars + profile screen redesign')
            ->delete();
    }
};
