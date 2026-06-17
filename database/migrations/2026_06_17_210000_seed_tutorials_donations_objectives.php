<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Dashboard: movable boxes (new bullet).
        'tutorials.topic.dashboard.bullet.5' => [
            'es' => '**Cajas personalizables**: pulsa "Reorganizar" (arriba a la derecha del panel) y arrastra las cajas para ponerlas en el orden que quieras; el orden se guarda en tu navegador. "Restablecer" vuelve al orden por defecto.',
            'en' => '**Customizable boxes**: hit "Rearrange" (top-right of the panel) and drag the boxes into whatever order you like; the order is saved in your browser. "Reset" restores the default.',
        ],

        // Donations topic (member).
        'tutorials.topic.donations.title' => [
            'es' => 'Donar a la CP',
            'en' => 'Donate to the CP',
        ],
        'tutorials.topic.donations.intro' => [
            'es' => 'Puedes donar **adena o items** a tu CP desde el dashboard (caja de Donaciones). Cada donación entra como reporte **pendiente en [/loot](/loot)** para que un oficial la revise y confirme — así queda registrada y auditable.',
            'en' => 'You can donate **adena or items** to your CP from the dashboard (Donations box). Each donation lands as a **pending report in [/loot](/loot)** for an officer to review and confirm — fully logged and auditable.',
        ],
        'tutorials.topic.donations.bullet.0' => [
            'es' => '**Cómo donar**: en la caja de Donaciones del dashboard, pulsa "Donar adena" o "Donar item", elige cantidad (y el item) y envía. Si tu CP exige captura, adjunta una.',
            'en' => '**How to donate**: in the dashboard Donations box, hit "Donate adena" or "Donate item", pick the amount (and item) and send. Attach a screenshot if your CP requires one.',
        ],
        'tutorials.topic.donations.bullet.1' => [
            'es' => '**Revisión**: la donación entra como **pendiente en /loot**. El líder o el contable la confirma o rechaza, igual que un reporte de loot normal.',
            'en' => '**Review**: the donation lands as **pending in /loot**. The leader or accountant confirms or rejects it, just like a regular loot report.',
        ],
        'tutorials.topic.donations.bullet.2' => [
            'es' => '**Con tracker DKP**: al confirmarse, la donación **suma puntos** al donante (`valor ÷ divisor`), sin tocar tu saldo de adena — es una donación, la CP no te debe nada. El ranking de esa caja es el del tracker.',
            'en' => '**With the DKP tracker**: once confirmed, the donation **awards points** to the donor (`value ÷ divisor`), without touching your adena balance — it\'s a gift, the CP owes you nothing. That box shows the tracker ranking.',
        ],
        'tutorials.topic.donations.bullet.3' => [
            'es' => '**Sin tracker**: las donaciones quedan registradas y el ranking se ordena por **valor donado** (últimos 7 días).',
            'en' => '**Without a tracker**: donations are logged and the ranking is ordered by **donated value** (last 7 days).',
        ],
        'tutorials.topic.donations.bullet.4' => [
            'es' => '**Objetivos**: si el item donado es un **objetivo semanal**, los puntos se multiplican por el multiplicador del objetivo (ver "Objetivos semanales").',
            'en' => '**Objectives**: if the donated item is a **weekly objective**, the points are multiplied by the objective\'s multiplier (see "Weekly objectives").',
        ],

        // Weekly objectives topic (leader/accountant).
        'tutorials.topic.weekly_objectives.title' => [
            'es' => 'Objetivos semanales',
            'en' => 'Weekly objectives',
        ],
        'tutorials.topic.weekly_objectives.intro' => [
            'es' => 'Marca **items que la CP busca**, cada uno con una cantidad objetivo y un **multiplicador** de puntos. Mientras el objetivo esté activo, conseguir ese item (farmeando o por donación) otorga `puntos × multiplicador`. Lo gestionan **admin, líder y contable**.',
            'en' => 'Flag **items the CP is hunting**, each with a target quantity and a points **multiplier**. While an objective is active, getting that item (farming or donation) grants `points × multiplier`. Managed by **admin, leader and accountant**.',
        ],
        'tutorials.topic.weekly_objectives.bullet.0' => [
            'es' => '**Cómo**: en la caja de Objetivos del dashboard, pulsa "Añadir", busca el item, y fija la **cantidad objetivo** y el **multiplicador** (ej. ×1,5).',
            'en' => '**How**: in the dashboard Objectives box, hit "Add", search the item, and set the **target quantity** and the **multiplier** (e.g. ×1.5).',
        ],
        'tutorials.topic.weekly_objectives.bullet.1' => [
            'es' => '**Bonus de puntos**: con el tracker DKP activo, ese item da `(valor ÷ divisor) × multiplicador` puntos al conseguirlo, tanto por loot como por donación.',
            'en' => '**Points bonus**: with the DKP tracker on, that item awards `(value ÷ divisor) × multiplier` points when obtained, via loot or donation.',
        ],
        'tutorials.topic.weekly_objectives.bullet.2' => [
            'es' => '**Progreso y completado**: una barra muestra lo conseguido / objetivo. Al alcanzar la cantidad, el objetivo se **completa** y el item vuelve a dar puntos normales.',
            'en' => '**Progress & completion**: a bar shows obtained / target. On reaching the target the objective is **completed** and the item reverts to normal points.',
        ],
        'tutorials.topic.weekly_objectives.bullet.3' => [
            'es' => '**Renovación**: al añadir nuevos objetivos, los **completados se borran** y quedan los pendientes + los nuevos — para incentivar terminar lo que falta.',
            'en' => '**Refresh**: adding new objectives **purges the completed** ones and keeps the pending plus the new — to push finishing what\'s left.',
        ],
        'tutorials.topic.weekly_objectives.bullet.4' => [
            'es' => '**Sin tracker**: puedes ponerlos igual, pero son **informativos** — no otorgan puntos porque no hay sistema de DKP.',
            'en' => '**Without a tracker**: you can still set them, but they\'re **informational** — no points, since there\'s no DKP system.',
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
