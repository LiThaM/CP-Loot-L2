<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Customizable dashboard: drag the boxes'],
            [
                'audience' => 'web',
                'title_es' => 'Dashboard personalizable: mueve las cajas',
                'title_en' => 'Customizable dashboard: drag the boxes',
                'body_es' => <<<'MD'
Tu dashboard ahora es personalizable. Pulsa **Reorganizar** (arriba a la derecha del panel) y arrastra las cajas para ponerlas en el orden que quieras: donaciones, actividad, drops, rankings, resumen… El orden se guarda en tu navegador, así que la próxima vez lo encuentras igual. **Restablecer** vuelve al orden por defecto.
MD,
                'body_en' => <<<'MD'
Your dashboard is now customizable. Hit **Rearrange** (top-right of the panel) and drag the boxes into whatever order you like: donations, activity, drops, rankings, summary… The order is saved in your browser so it stays put next time. **Reset** brings back the default order.
MD,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')->where('title_en', 'Customizable dashboard: drag the boxes')->delete();
    }
};
