<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'CP donations: ranking & weekly goal'],
            [
                'audience' => 'web',
                'title_es' => 'Donaciones a la CP: ranking y objetivo semanal',
                'title_en' => 'CP donations: ranking & weekly goal',
                'body_es' => <<<'MD'
Ahora puedes **donar al fondo de la CP** desde el dashboard: adena **y también items** (se valoran a precio de mercado). El panel muestra un **ranking de donantes** (últimos 7 días) y un **objetivo semanal**: el líder fija una meta de adena y una barra se va llenando con todo lo donado esa semana (adena + items). Si no hay meta, verás igualmente el total donado.
MD,
                'body_en' => <<<'MD'
You can now **donate to the CP fund** straight from the dashboard: adena **and items too** (valued at market price). The panel shows a **donor ranking** (last 7 days) and a **weekly goal**: the leader sets an adena target and a bar fills with everything donated that week (adena + items). With no goal set you still see the weekly donated total.
MD,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')->where('title_en', 'CP donations: ranking & weekly goal')->delete();
    }
};
