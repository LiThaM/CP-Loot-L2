<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "Health sprint" entry I shipped earlier today used developer
 * vocabulary (mass-assignment, N+1 queries, file paths under
 * resources/js/utils/…). Rewrite both title and body in plain user
 * language. No structural changes — same row, same audience, same
 * published_at.
 */
return new class extends Migration
{
    private const ORIGINAL_TITLE_EN = 'Health sprint: security, performance, dedupe';
    private const NEW_TITLE_EN = 'Invisible speed and security improvements';
    private const NEW_TITLE_ES = 'Mejoras invisibles de velocidad y seguridad';

    public function up(): void
    {
        $bodyEs = <<<'MD'
Pase de fondo sin features nuevas: la pestaña Historial de [/loot](/loot) carga bastante más rápido en CPs con histórico largo, y hemos limado un par de bordes en cómo se guardan los datos sensibles del perfil. Nada nuevo que aprender — si notas el navegar más fluido, es esto.
MD;

        $bodyEn = <<<'MD'
Behind-the-scenes pass with no new features: the History tab on [/loot](/loot) loads noticeably faster on CPs with deep history, and we tightened a couple of edges around how sensitive profile data is stored. Nothing new to learn — if the app feels snappier, this is why.
MD;

        DB::table('changelog_entries')
            ->where('title_en', self::ORIGINAL_TITLE_EN)
            ->update([
                'title_es' => self::NEW_TITLE_ES,
                'title_en' => self::NEW_TITLE_EN,
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op: the original technical body is the "wrong" copy we
        // came from. Re-running this migration would just overwrite
        // again with the user-friendly version, so rollback is a no-op.
    }
};
