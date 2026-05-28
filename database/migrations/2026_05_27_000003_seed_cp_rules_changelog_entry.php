<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
- **Normas de la CP:** los líderes pueden publicar las reglas internas del CP en [/party](/party) → pestaña Normas. Soporta `**negrita**`, *cursiva*, `código` y [enlaces](/loot).
- **Aceptación bloqueante:** cada miembro tiene que leer y aceptar las normas antes de seguir operando. Si el líder edita y guarda una nueva versión, todos los miembros vuelven a ver el modal de aceptación.
- El líder se auto-acepta al guardar — no le vuelve a salir el modal en su sesión.
MD;

        $bodyEn = <<<'MD'
- **CP rules:** leaders can publish their CP's internal rules from [/party](/party) → Rules tab. Supports `**bold**`, *italic*, `code` and [links](/loot).
- **Blocking acceptance:** every member has to read and accept before continuing. When the leader edits and saves a new version, every member has to re-accept.
- The leader auto-accepts on save — no extra modal in their own session.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'CP rules with blocking member acceptance'],
            [
                'audience' => 'web',
                'title_es' => 'Normas de CP con aceptación bloqueante por miembros',
                'title_en' => 'CP rules with blocking member acceptance',
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
            ->where('title_en', 'CP rules with blocking member acceptance')
            ->delete();
    }
};
