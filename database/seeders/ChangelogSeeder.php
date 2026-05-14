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
                'title_es' => 'Nuevo apartado de cambios (Changelog)',
                'title_en' => 'New Changelog section',
                'body_es' => 'Ahora puedes seguir las novedades de la web desde el icono de la estrella en la barra superior. Cada cambio importante se publicará aquí en español e inglés.',
                'body_en' => 'You can now follow site updates from the star icon in the top bar. Every notable change will be published here in Spanish and English.',
            ],
            [
                'published_at' => '2026-05-14 12:30:00',
                'type' => 'feature',
                'title_es' => 'Notificaciones por email vía Mailgun',
                'title_en' => 'Email notifications via Mailgun',
                'body_es' => 'Cuando un usuario abre un ticket de soporte se envía un email al equipo y una confirmación al autor. Lo mismo para respuestas y nuevas solicitudes de CP.',
                'body_en' => 'When a user opens a support ticket we email the team and confirm receipt to the author. Same for replies and new CP requests.',
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
                'published_at' => '2026-05-12 16:00:00',
                'type' => 'fix',
                'title_es' => 'Traducciones de login y registro en inglés',
                'title_en' => 'English translations on login and register',
                'body_es' => 'Las pantallas de inicio de sesión y registro mostraban textos genéricos como "Title" o "Subtitle" en inglés. Ahora muestran los textos correctos.',
                'body_en' => 'Login and register screens were showing placeholder text such as "Title" or "Subtitle". They now show proper English copy.',
            ],

            // May 06 — Crafting & recipes overhaul
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
                'title_es' => 'Explorador de recetas multi-chronicle (L2Hub)',
                'title_en' => 'Multi-chronicle recipe explorer (L2Hub)',
                'body_es' => 'Importador de recetas desde L2Hub para múltiples chronicles. Filtro de chronicle añadido al explorador público.',
                'body_en' => 'Recipe importer from L2Hub for multiple chronicles. Added chronicle filter to the public explorer.',
            ],
            [
                'published_at' => '2026-05-06 10:00:00',
                'type' => 'fix',
                'title_es' => 'Limpieza de items con nombres rotos o imágenes inválidas',
                'title_en' => 'Cleanup of items with broken names or invalid images',
                'body_es' => 'Comando de mantenimiento mejorado para detectar y arreglar ítems con nombres mal formateados o URLs de imagen rotas.',
                'body_en' => 'Improved maintenance command to detect and fix items with broken names or invalid image URLs.',
            ],

            // May 04 — Landing & public features
            [
                'published_at' => '2026-05-04 18:00:00',
                'type' => 'feature',
                'title_es' => 'Rediseño completo de la landing',
                'title_en' => 'Complete landing page redesign',
                'body_es' => 'Nueva landing con un look profesional de SaaS, mejor jerarquía visual y CTA más claros.',
                'body_en' => 'New landing with a professional SaaS look, better visual hierarchy and clearer CTAs.',
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
                'published_at' => '2026-05-04 10:00:00',
                'type' => 'fix',
                'title_es' => 'Soporte público requiere email; bloqueo de miembros inactivos',
                'title_en' => 'Public support requires email; inactive members blocked',
                'body_es' => 'El formulario de soporte público pide email obligatorio. Los miembros de CPs inactivas dejan de tener acceso.',
                'body_en' => 'Public support form requires email. Members of inactive CPs lose access.',
            ],
            [
                'published_at' => '2026-05-04 09:00:00',
                'type' => 'feature',
                'title_es' => 'Nuevo logo AdenaLedger',
                'title_en' => 'New AdenaLedger logo',
                'body_es' => 'Logo SVG nuevo que acompaña el rebranding y el rediseño de la landing.',
                'body_en' => 'New SVG logo matching the rebrand and landing redesign.',
            ],

            // April 21 — Tickets & activity
            [
                'published_at' => '2026-04-21 18:00:00',
                'type' => 'feature',
                'title_es' => 'Tracking de actividad y preferencias de usuario',
                'title_en' => 'User activity tracking and preferences',
                'body_es' => 'Registramos la actividad de cada usuario en la app y añadimos un panel de preferencias personales.',
                'body_en' => 'We track per-user activity inside the app and added a personal preferences panel.',
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
                'published_at' => '2026-04-21 10:00:00',
                'type' => 'feature',
                'title_es' => 'Sistema completo de tickets',
                'title_en' => 'Full ticket system',
                'body_es' => 'Sistema de tickets con tipos (bug/discrepancia de datos), asignación a líderes y estados.',
                'body_en' => 'Ticket system with types (bug/data discrepancy), leader assignment and statuses.',
            ],
            [
                'published_at' => '2026-04-21 09:00:00',
                'type' => 'improvement',
                'title_es' => 'Filtro de tipo en historial de loot e indicador de craft',
                'title_en' => 'History type filter and craft success indicator',
                'body_es' => 'El historial de loot tiene filtros por tipo de evento y muestra el resultado (éxito/fallo) de cada craft.',
                'body_en' => 'Loot history has event-type filters and shows the result (success/fail) of each craft.',
            ],

            // April 15-16 — i18n, tickets, pagination
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
                'title_es' => 'Soporte multi-idioma y panel de traducciones',
                'title_en' => 'Multi-language support and translation panel',
                'body_es' => 'Cambio dinámico de idioma y panel de administración para editar todas las traducciones desde la web.',
                'body_en' => 'Dynamic locale switching and an admin panel to edit every translation from the web.',
            ],
            [
                'published_at' => '2026-04-15 14:00:00',
                'type' => 'feature',
                'title_es' => 'Sistema de tickets de soporte (v1)',
                'title_en' => 'Support ticket system (v1)',
                'body_es' => 'Primera versión del sistema de tickets desde la web.',
                'body_en' => 'First version of the in-app support ticket system.',
            ],
            [
                'published_at' => '2026-04-15 12:00:00',
                'type' => 'fix',
                'title_es' => 'Adena y puntos con valores grandes (BigInt)',
                'title_en' => 'Big-number Adena and points handling',
                'body_es' => 'Ya no se truncan los valores grandes de Adena ni puntos en la UI.',
                'body_en' => 'Big Adena and point values no longer overflow in the UI.',
            ],
            [
                'published_at' => '2026-04-15 10:00:00',
                'type' => 'improvement',
                'title_es' => 'Adena por miembro inline + paginación en búsqueda',
                'title_en' => 'Per-member Adena inline + search pagination',
                'body_es' => 'Vemos el Adena por miembro directamente en línea. La búsqueda de items soporta paginación y "cargar más".',
                'body_en' => 'Per-member Adena shown inline. Item search supports pagination and "load more".',
            ],

            // April 11 — Big batch
            [
                'published_at' => '2026-04-11 18:00:00',
                'type' => 'feature',
                'title_es' => 'Rebranding a AdenaLedger',
                'title_en' => 'Rebrand to AdenaLedger',
                'body_es' => 'El proyecto pasa de llamarse "CP-Loot-L2" a "AdenaLedger".',
                'body_en' => 'The project changes its name from "CP-Loot-L2" to "AdenaLedger".',
            ],
            [
                'published_at' => '2026-04-11 16:00:00',
                'type' => 'feature',
                'title_es' => 'Sistema de crafteo, audit alerts y modo oscuro',
                'title_en' => 'Crafting system, audit alerts and dark mode',
                'body_es' => 'Nuevo sistema de crafteo, alertas auditables para acciones importantes y modo oscuro en toda la web.',
                'body_en' => 'New crafting system, audit alerts for important actions and dark mode site-wide.',
            ],
            [
                'published_at' => '2026-04-11 14:00:00',
                'type' => 'feature',
                'title_es' => 'Soft delete de usuarios y página de excluidos',
                'title_en' => 'Soft-deleted users and excluded page',
                'body_es' => 'Los usuarios se desactivan en vez de borrarse, con página específica para los excluidos.',
                'body_en' => 'Users are deactivated rather than deleted, with a dedicated excluded page.',
            ],

            // April 10
            [
                'published_at' => '2026-04-10 18:00:00',
                'type' => 'feature',
                'title_es' => 'Charts de dashboard y mejoras de gestión de CP',
                'title_en' => 'Dashboard charts and CP management improvements',
                'body_es' => 'Gráficos en el dashboard para visualizar actividad y mejoras en la gestión de CPs.',
                'body_en' => 'Dashboard charts for activity visibility and improvements to CP management.',
            ],
            [
                'published_at' => '2026-04-10 14:00:00',
                'type' => 'feature',
                'title_es' => 'Sistema principal de loot, wishlist y gestión de usuarios',
                'title_en' => 'Core loot system, wishlist and user management',
                'body_es' => 'Primera versión del core: registro de loot, wishlist y herramientas administrativas para gestionar usuarios.',
                'body_en' => 'First version of the core: loot reporting, wishlist and admin tools to manage users.',
            ],

            // April 07 — initial release
            [
                'published_at' => '2026-04-07 12:00:00',
                'type' => 'feature',
                'title_es' => 'Lanzamiento inicial de AdenaLedger',
                'title_en' => 'AdenaLedger initial release',
                'body_es' => 'Arranca el proyecto sobre Laravel, Vue 3, Inertia y Tailwind, con la arquitectura base del gestor de loot y Adena.',
                'body_en' => 'Project kicks off on Laravel, Vue 3, Inertia and Tailwind, with the base architecture for the loot and Adena manager.',
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
