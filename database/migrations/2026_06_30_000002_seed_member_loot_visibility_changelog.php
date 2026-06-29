<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against duplicate seeding if the migration runs twice against
        // the shared remote DB (local + server both pointing at it).
        if (DB::table('changelog_entries')->where('title_es', 'Mira el loot de cada miembro — filtro y aportaciones')->exists()) {
            return;
        }

        DB::table('changelog_entries')->insert([
            'type'       => 'feature',
            'audience'   => 'web',
            'title_es'   => 'Mira el loot de cada miembro — filtro y aportaciones',
            'title_en'   => "See each member's loot — filter & contributions",
            'title_it'   => 'Vedi il loot di ogni membro — filtro e contributi',
            'title_ru'   => 'Смотрите лут каждого участника — фильтр и вклад',
            'body_es'    => "**Filtro por miembro en /loot** — La barra de búsqueda del historial y de las sesiones pendientes tiene ahora un desplegable con todos los miembros de la CP. Elige a uno y verás solo los reportes en los que participó como asistente. Se acabó escribir el nombre a mano.\n\n**«Lo aportado» en /party** — Al desplegar la ficha de un miembro ahora ves los ítems que aportó en las sesiones de farm a las que asistió, no solo lo que se le asignó. Auditoría más transparente de quién trae qué al almacén.",
            'body_en'    => "**Member filter in /loot** — The search bar in both history and pending sessions now has a dropdown with every CP member. Pick one and you'll only see the reports where they took part as an attendee. No more typing names by hand.\n\n**«Contributions» in /party** — Expanding a member's card now shows the items they contributed in the farm sessions they attended, not just what was assigned to them. A more transparent audit of who brings what to the warehouse.",
            'body_it'    => "**Filtro per membro in /loot** — La barra di ricerca dello storico e delle sessioni in attesa ha ora un menu a tendina con tutti i membri della CP. Selezionane uno e vedrai solo i report in cui ha partecipato come presente. Niente più nomi da scrivere a mano.\n\n**«Contributi» in /party** — Espandendo la scheda di un membro ora vedi gli oggetti che ha contribuito nelle sessioni di farm a cui ha partecipato, non solo ciò che gli è stato assegnato. Un audit più trasparente di chi porta cosa al magazzino.",
            'body_ru'    => "**Фильтр по участнику в /loot** — В строке поиска истории и ожидающих сессий теперь есть выпадающий список со всеми участниками CP. Выберите одного — и увидите только отчёты, где он присутствовал. Больше не нужно вводить имя вручную.\n\n**«Вклад» в /party** — При раскрытии карточки участника теперь видны предметы, которые он принёс на фарм-сессиях, где присутствовал, а не только то, что ему назначили. Более прозрачный аудит того, кто что приносит на склад.",
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_es', 'Mira el loot de cada miembro — filtro y aportaciones')
            ->delete();
    }
};
