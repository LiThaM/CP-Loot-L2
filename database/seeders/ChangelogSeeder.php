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
                'published_at' => '2026-05-14 12:30:00',
                'type' => 'feature',
                'title_es' => 'Notificaciones por email vía Mailgun',
                'title_en' => 'Email notifications via Mailgun',
                'body_es' => 'Cuando un usuario abre un ticket de soporte se envía un email al equipo y una confirmación al autor. Lo mismo para respuestas y nuevas solicitudes de CP.',
                'body_en' => 'When a user opens a support ticket we email the team and confirm receipt to the author. Same for replies and new CP requests.',
            ],
            [
                'published_at' => '2026-05-14 12:00:00',
                'type' => 'feature',
                'title_es' => 'Nuevo apartado de cambios (Changelog)',
                'title_en' => 'New Changelog section',
                'body_es' => 'Ahora puedes seguir las novedades de la web desde el icono de la estrella en la barra superior. Cada cambio importante se publicará aquí en español e inglés.',
                'body_en' => 'You can now follow site updates from the star icon in the top bar. Every notable change will be published here in Spanish and English.',
            ],
            [
                'published_at' => '2026-05-12 16:00:00',
                'type' => 'fix',
                'title_es' => 'Traducciones de login y registro en inglés',
                'title_en' => 'English translations on login and register',
                'body_es' => 'Las pantallas de inicio de sesión y registro mostraban textos genéricos como "Title" o "Subtitle" en inglés. Ahora muestran los textos correctos.',
                'body_en' => 'Login and register screens were showing placeholder text such as "Title" or "Subtitle". They now show proper English copy.',
            ],
            [
                'published_at' => '2026-05-11 18:00:00',
                'type' => 'feature',
                'title_es' => 'Reiniciar puntos DKP de la CP',
                'title_en' => 'Reset CP DKP points',
                'body_es' => 'El líder de la CP puede reiniciar de un solo clic los puntos DKP de todos los miembros desde la pestaña de Puntos. El historial de Adena no se ve afectado.',
                'body_en' => 'CP leaders can reset every member\'s DKP points in one click from the Points tab. Adena history is preserved.',
            ],
            [
                'published_at' => '2026-05-11 17:00:00',
                'type' => 'improvement',
                'title_es' => 'Botones de gestión visibles en tu propia fila',
                'title_en' => 'Management buttons visible on your own row',
                'body_es' => 'Los botones de pago y edición ahora son visibles también en la fila del usuario actual dentro de la pestaña de miembros. El botón de baneo se mantiene oculto al actuar sobre ti mismo.',
                'body_en' => 'Pay and edit buttons are now visible on your own row in the members tab. The ban button stays hidden when acting on yourself.',
            ],
            [
                'published_at' => '2026-05-06 18:00:00',
                'type' => 'feature',
                'title_es' => 'Auto-crafteo recursivo de submateriales',
                'title_en' => 'Recursive auto-crafting of sub-materials',
                'body_es' => 'Al ejecutar una receta, el sistema crafteará automáticamente los materiales intermedios necesarios desde el warehouse a cualquier profundidad.',
                'body_en' => 'When executing a recipe, the system auto-crafts intermediate materials from the warehouse at any depth.',
            ],
            [
                'published_at' => '2026-05-06 16:00:00',
                'type' => 'feature',
                'title_es' => 'Badge de "puedes craftear" en materiales',
                'title_en' => '"Can craft" badge on materials',
                'body_es' => 'Si tienes los submateriales en el warehouse, los materiales craftables muestran un badge con la cantidad que podrías producir.',
                'body_en' => 'When you have the sub-materials in the warehouse, craftable materials show a badge with the amount you could produce.',
            ],
            [
                'published_at' => '2026-05-06 14:00:00',
                'type' => 'improvement',
                'title_es' => 'Nueva calculadora de recetas estilo l2hub',
                'title_en' => 'New l2hub-style recipe calculator',
                'body_es' => 'Calculadora rediseñada con sliders y cantidades por item para materiales crafteables, mucho más cómoda de usar.',
                'body_en' => 'Redesigned calculator with per-item quantities and sliders for craftable materials. Much easier to use.',
            ],
            [
                'published_at' => '2026-05-06 12:00:00',
                'type' => 'feature',
                'title_es' => 'Explorador de recetas con filtro de chronicle',
                'title_en' => 'Recipe explorer with chronicle filter',
                'body_es' => 'El explorador de recetas soporta múltiples chronicles y permite filtrar el catálogo por la que estés jugando.',
                'body_en' => 'The recipe explorer supports multiple chronicles and lets you filter the catalog by the one you play.',
            ],
            [
                'published_at' => '2026-05-04 16:00:00',
                'type' => 'feature',
                'title_es' => 'Explorador público de recetas (/recipes)',
                'title_en' => 'Public recipe explorer (/recipes)',
                'body_es' => 'Cualquiera puede explorar recetas y ver el árbol de crafteo sin necesidad de registrarse.',
                'body_en' => 'Anyone can explore recipes and view the crafting tree without signing up.',
            ],
            [
                'published_at' => '2026-05-04 14:00:00',
                'type' => 'feature',
                'title_es' => 'Donaciones libres al fondo de la CP',
                'title_en' => 'Free-form donations to the CP fund',
                'body_es' => 'Los miembros pueden donar cualquier cantidad al fondo de la CP, no solo el saldo que deben.',
                'body_en' => 'Members can donate any amount to the CP fund, not just what they owe.',
            ],
            [
                'published_at' => '2026-05-04 12:00:00',
                'type' => 'feature',
                'title_es' => 'Admin: deshabilitar/borrar CPs y dashboard mejorado',
                'title_en' => 'Admin: disable/delete CPs and improved dashboard',
                'body_es' => 'Los administradores pueden deshabilitar o borrar CPs y ver estadísticas más completas.',
                'body_en' => 'Admins can disable or delete CPs and see more complete dashboard stats.',
            ],
            [
                'published_at' => '2026-04-21 14:00:00',
                'type' => 'feature',
                'title_es' => 'Adjuntos en tickets de soporte',
                'title_en' => 'Attachments in support tickets',
                'body_es' => 'Los tickets de soporte ahora aceptan imágenes y vídeos como adjuntos.',
                'body_en' => 'Support tickets now accept images and videos as attachments.',
            ],
            [
                'published_at' => '2026-04-21 09:00:00',
                'type' => 'improvement',
                'title_es' => 'Filtro de tipo en historial de loot e indicador de craft',
                'title_en' => 'History type filter and craft success indicator',
                'body_es' => 'El historial de loot tiene filtros por tipo de evento y muestra el resultado (éxito/fallo) de cada craft.',
                'body_en' => 'Loot history has event-type filters and shows the result (success/fail) of each craft.',
            ],
            [
                'published_at' => '2026-04-16 12:00:00',
                'type' => 'improvement',
                'title_es' => 'Paginación y búsqueda en el historial de loot',
                'title_en' => 'Pagination and search in loot history',
                'body_es' => 'El historial de loot soporta paginación y búsqueda para CPs con mucho volumen.',
                'body_en' => 'Loot history supports pagination and search for high-volume CPs.',
            ],
            [
                'published_at' => '2026-04-15 18:00:00',
                'type' => 'feature',
                'title_es' => 'Compras en el warehouse y campo de descripción',
                'title_en' => 'Warehouse purchases and description field',
                'body_es' => 'Los líderes pueden registrar compras del warehouse con descripción asociada.',
                'body_en' => 'Leaders can register warehouse purchases with an associated description.',
            ],
            [
                'published_at' => '2026-04-15 16:00:00',
                'type' => 'feature',
                'title_es' => 'Soporte multi-idioma',
                'title_en' => 'Multi-language support',
                'body_es' => 'Cambio dinámico entre español e inglés en toda la web.',
                'body_en' => 'Dynamic Spanish/English switching across the whole site.',
            ],
            [
                'published_at' => '2026-04-15 14:00:00',
                'type' => 'feature',
                'title_es' => 'Sistema de tickets de soporte',
                'title_en' => 'Support ticket system',
                'body_es' => 'Sistema de tickets dentro de la app con asignación a líderes y estados.',
                'body_en' => 'In-app ticket system with leader assignment and statuses.',
            ],
            [
                'published_at' => '2026-04-15 10:00:00',
                'type' => 'improvement',
                'title_es' => 'Adena por miembro inline + paginación en búsqueda',
                'title_en' => 'Per-member Adena inline + search pagination',
                'body_es' => 'Vemos el Adena por miembro directamente en línea. La búsqueda de items soporta paginación y "cargar más".',
                'body_en' => 'Per-member Adena shown inline. Item search supports pagination and "load more".',
            ],
            [
                'published_at' => '2026-04-11 16:00:00',
                'type' => 'feature',
                'title_es' => 'Sistema de crafteo, alertas de auditoría y modo oscuro',
                'title_en' => 'Crafting system, audit alerts and dark mode',
                'body_es' => 'Nuevo sistema de crafteo, alertas auditables para acciones importantes y modo oscuro en toda la web.',
                'body_en' => 'New crafting system, audit alerts for important actions and dark mode site-wide.',
            ],
            [
                'published_at' => '2026-04-10 18:00:00',
                'type' => 'feature',
                'title_es' => 'Gráficos en el dashboard',
                'title_en' => 'Dashboard charts',
                'body_es' => 'Gráficos en el dashboard para visualizar actividad de loot y CP.',
                'body_en' => 'Charts on the dashboard to visualize loot and CP activity.',
            ],
            [
                'published_at' => '2026-04-10 14:00:00',
                'type' => 'feature',
                'title_es' => 'Sistema principal de loot y wishlist',
                'title_en' => 'Core loot system and wishlist',
                'body_es' => 'Primera versión del core: registro de loot, wishlist de items y herramientas básicas de gestión.',
                'body_en' => 'First version of the core: loot reporting, item wishlist and basic management tools.',
            ],
            [
                'published_at' => '2026-04-07 12:00:00',
                'type' => 'feature',
                'title_es' => 'Lanzamiento inicial',
                'title_en' => 'Initial release',
                'body_es' => 'Arranca el proyecto con el gestor de loot y Adena para CPs de Lineage II.',
                'body_en' => 'The project launches with the loot and Adena manager for Lineage II CPs.',
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
