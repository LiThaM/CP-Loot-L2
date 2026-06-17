<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // The earlier donations design (cp_donations ledger + adena weekly goal)
        // was reworked; drop its now-inaccurate changelog entry if it landed.
        DB::table('changelog_entries')->where('title_en', 'CP donations: ranking & weekly goal')->delete();

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'CP donations & weekly objectives'],
            [
                'audience' => 'web',
                'title_es' => 'Donaciones a la CP y objetivos semanales',
                'title_en' => 'CP donations & weekly objectives',
                'body_es' => <<<'MD'
**Donaciones**: ahora puedes donar adena o items a la CP desde el dashboard. Cada donación entra en **/loot como pendiente** para que el líder la revise y confirme (con captura si tu CP la exige). En CPs con **tracker DKP**, la donación **da puntos** al donante (valor ÷ divisor) y el ranking pasa a ser el del tracker; sin tracker queda como valor donado.

**Objetivos semanales**: el líder marca **items que la CP busca**, con cantidad objetivo y un **multiplicador** (ej. ×1,5). Mientras el objetivo esté activo, conseguir ese item (farmeando o por donación) da **puntos multiplicados**. Al completarlo vuelve a la normalidad; al añadir nuevos, se limpian los completados y quedan los pendientes. Sin tracker los objetivos son informativos.
MD,
                'body_en' => <<<'MD'
**Donations**: you can now donate adena or items to the CP from the dashboard. Each donation lands in **/loot as pending** for the leader to review and confirm (with a screenshot if your CP requires one). On CPs with the **DKP tracker**, a donation **awards points** to the donor (value ÷ divisor) and the box becomes the tracker ranking; without a tracker it stays as donated value.

**Weekly objectives**: leaders flag **items the CP is hunting**, with a target quantity and a **multiplier** (e.g. ×1.5). While an objective is active, getting that item (farming or donation) grants **multiplied points**. Completing it reverts to normal; adding new ones clears the completed and keeps the pending. Without a tracker the objectives are informational.
MD,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'fix', 'version' => null, 'title_en' => 'Dark mode applies instantly'],
            [
                'audience' => 'web',
                'title_es' => 'El modo oscuro se aplica al instante',
                'title_en' => 'Dark mode applies instantly',
                'body_es' => 'Al cambiar el tema (claro/oscuro/sistema) en tu perfil, ahora se aplica al momento sin necesidad de recargar la página.',
                'body_en' => 'Switching theme (light/dark/system) in your profile now applies immediately, no page reload needed.',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')->whereIn('title_en', [
            'CP donations & weekly objectives',
            'Dark mode applies instantly',
        ])->delete();
    }
};
