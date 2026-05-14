<?php

namespace Database\Seeders;

use App\Contexts\System\Domain\Models\ChangelogEntry;
use Illuminate\Database\Seeder;

class ChangelogSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'published_at' => '2026-05-14 12:00:00',
                'type' => 'feature',
                'version' => null,
                'title_es' => 'Nuevo apartado de cambios (Changelog)',
                'title_en' => 'New Changelog section',
                'body_es' => 'Ahora puedes seguir las novedades de la web desde tu menú de usuario. Cada cambio importante se publicará aquí en español e inglés.',
                'body_en' => 'You can now follow site updates from your user menu. Every notable change will be published here in Spanish and English.',
            ],
            [
                'published_at' => '2026-05-11 18:00:00',
                'type' => 'feature',
                'version' => null,
                'title_es' => 'Reiniciar puntos DKP de la CP',
                'title_en' => 'Reset CP DKP points',
                'body_es' => 'El líder de la CP puede reiniciar de un solo clic los puntos DKP de todos los miembros desde la pestaña de Puntos. El historial de Adena no se ve afectado.',
                'body_en' => 'CP leaders can reset every member\'s DKP points in one click from the Points tab. Adena history is preserved.',
            ],
            [
                'published_at' => '2026-05-11 17:00:00',
                'type' => 'improvement',
                'version' => null,
                'title_es' => 'Botones de gestión visibles en tu propia fila',
                'title_en' => 'Management buttons visible on your own row',
                'body_es' => 'Los botones de pago y edición ahora son visibles también en la fila del usuario actual dentro de la pestaña de miembros. El botón de baneo se mantiene oculto al actuar sobre ti mismo.',
                'body_en' => 'Pay and edit buttons are now visible on your own row in the members tab. The ban button stays hidden when acting on yourself.',
            ],
            [
                'published_at' => '2026-05-11 16:00:00',
                'type' => 'fix',
                'version' => null,
                'title_es' => 'Traducciones de login y registro en inglés',
                'title_en' => 'English translations on login and register',
                'body_es' => 'Las pantallas de inicio de sesión y registro mostraban textos genéricos como "Title" o "Subtitle" en inglés. Ahora muestran los textos correctos.',
                'body_en' => 'Login and register screens were showing placeholder text such as "Title" or "Subtitle". They now show proper English copy.',
            ],
            [
                'published_at' => '2026-05-10 12:00:00',
                'type' => 'feature',
                'version' => null,
                'title_es' => 'Auto-crafteo recursivo de submateriales',
                'title_en' => 'Recursive auto-crafting of sub-materials',
                'body_es' => 'Al ejecutar una receta, el sistema crafteará automáticamente los materiales intermedios necesarios desde el warehouse a cualquier profundidad, no solo un nivel.',
                'body_en' => 'When executing a recipe, the system will auto-craft intermediate materials from the warehouse at any depth, not just one level.',
            ],
        ];

        foreach ($entries as $row) {
            ChangelogEntry::updateOrCreate(
                [
                    'title_es' => $row['title_es'],
                    'published_at' => $row['published_at'],
                ],
                $row,
            );
        }
    }
}
