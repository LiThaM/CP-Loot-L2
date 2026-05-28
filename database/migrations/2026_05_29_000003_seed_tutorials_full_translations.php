<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Full translation set for the /tutoriales page (Tutorials/Index.vue)
 * and the interactive tours (resources/js/utils/tour.js). Keys are
 * English-only by convention; values carry the ES + EN copy. The
 * earlier `…_seed_tutorials_translation.php` only seeded `nav.tutorials`;
 * this one fills out everything else.
 */
return new class extends Migration
{
    private array $translations = [
        // Page chrome
        'tutorials.page_title'       => ['es' => 'Tutoriales',                                                  'en' => 'Tutorials'],
        'tutorials.heading'          => ['es' => 'Tutoriales',                                                  'en' => 'Tutorials'],
        'tutorials.subtitle'         => ['es' => 'Qué hace cada cosa y cómo descubrir features que igual no has visto', 'en' => "What each thing does and how to discover features you might've missed"],
        'tutorials.your_role'        => ['es' => 'Tu rol actual',                                               'en' => 'Your current role'],
        'tutorials.see_also'         => ['es' => 'Ver también',                                                 'en' => 'See also'],
        'tutorials.expand'           => ['es' => 'expandir',                                                    'en' => 'expand'],
        'tutorials.collapse'         => ['es' => 'cerrar',                                                      'en' => 'collapse'],
        'tutorials.tours_available'  => ['es' => 'Tours interactivos disponibles',                              'en' => 'Available interactive tours'],

        // Role: member
        'tutorials.role.member.title'    => ['es' => 'Como miembro',                                            'en' => 'As a member'],
        'tutorials.role.member.intro'    => ['es' => 'Eres miembro de una CP. Puedes reportar tu loot, ver el almacén común, las normas y tu balance de adena/puntos.', 'en' => "You're a member of a CP. You can report your loot, browse the shared vault, read the rules and see your adena / points balance."],
        'tutorials.role.member.bullet.0' => ['es' => 'Reporta tu farm/boss desde **/loot** — la decisión final la toma el líder, pero el reporte lo abres tú.', 'en' => 'Report your farm/boss from **/loot** — the final call is on the leader, but you open the report.'],
        'tutorials.role.member.bullet.1' => ['es' => 'Mira el almacén compartido (CP Vault) y las recetas pinneadas en **/party**.', 'en' => 'Browse the shared vault (CP Vault) and pinned recipes in **/party**.'],
        'tutorials.role.member.bullet.2' => ['es' => 'En **/profile** registra tu personaje principal y todos los alts que quieras. Cuando reportes loot, podrás elegir con cuál farmeaste.', 'en' => 'In **/profile** register your main character and as many alts as you want. When you report loot you can pick which one farmed it.'],
        'tutorials.role.member.bullet.3' => ['es' => 'En la pestaña Normas de **/party** lees el reglamento del CP. Si el líder lo edita, te saldrá un aviso bloqueante hasta que aceptes la versión nueva.', 'en' => "In the Rules tab of **/party** you read the CP's rule book. If the leader edits it, you'll get a blocking modal until you accept the new version."],

        // Role: cp_leader
        'tutorials.role.cp_leader.title'    => ['es' => 'Como líder de CP',                                     'en' => 'As a CP leader'],
        'tutorials.role.cp_leader.intro'    => ['es' => 'Eres líder fundador o co-líder. Más allá de lo de miembro, decides cómo se reparte el loot, cómo se vende y qué normas hay.', 'en' => "You're a founder or co-leader. On top of the member stuff, you decide how loot is split, how items are sold, and what the rules are."],
        'tutorials.role.cp_leader.bullet.0' => ['es' => 'Apruebas loot pendiente desde **/loot**. Defines % al fondo, attendees y puntos.', 'en' => 'You approve pending loot from **/loot**. Set the % to the CP fund, attendees and points.'],
        'tutorials.role.cp_leader.bullet.1' => ['es' => 'En **/party** ajustas configuración (logo, server, captura obligatoria), defines los puntos por evento y publicas las normas del CP.', 'en' => 'In **/party** you tweak settings (logo, server, screenshot required), define event points and publish the CP rules.'],
        'tutorials.role.cp_leader.bullet.2' => ['es' => '**/party/craft-bulk**: calculadora para planear muchos crafts a la vez con desglose de materiales y sub-crafts.', 'en' => '**/party/craft-bulk**: a planner for crafting many recipes at once with a full material breakdown and auto sub-craft.'],
        'tutorials.role.cp_leader.bullet.3' => ['es' => 'La sección Pagos externos te muestra a qué externos hay que liquidar y desde dónde marcarlo pagado.', 'en' => 'The External payouts screen lists every external you still owe and lets you mark them paid.'],

        // Role: admin
        'tutorials.role.admin.title'    => ['es' => 'Como administrador',                                       'en' => 'As an administrator'],
        'tutorials.role.admin.intro'    => ['es' => 'Tienes acceso a todo el sistema — gestionas usuarios, CPs, items, releases, traducciones y crashes del bot desktop.', 'en' => 'You have full system access — you manage users, CPs, items, releases, translations and desktop-bot crashes.'],
        'tutorials.role.admin.bullet.0' => ['es' => '**/system/cps**: lista completa de CPs con filtros, impersonación, toggle active/inactive y borrado.', 'en' => '**/system/cps**: full CP roster with filters, impersonate, toggle active/inactive and delete.'],
        'tutorials.role.admin.bullet.1' => ['es' => '**/system/users**: lista global de cuentas, cambio de rol/CP, ajustes de adena, ban/unban, audit log por usuario.', 'en' => '**/system/users**: global account list, role/CP reassignment, adena adjustments, ban/unban, per-user audit log.'],
        'tutorials.role.admin.bullet.2' => ['es' => '**/system/items** y **/system/translations**: catálogo del juego y cadenas ES/EN.', 'en' => '**/system/items** and **/system/translations**: game catalogue and ES/EN string database.'],
        'tutorials.role.admin.bullet.3' => ['es' => '**/system/releases** y **/system/crashes**: pipeline del bot desktop y crashes agrupados por fingerprint.', 'en' => '**/system/releases** and **/system/crashes**: desktop bot pipeline and crashes grouped by fingerprint.'],

        // Tour: dashboard-overview
        'tour.dashboard-overview.title'        => ['es' => 'El Dashboard',                                      'en' => 'The Dashboard'],
        'tour.dashboard-overview.step.0.title' => ['es' => 'Bienvenido a tu dashboard',                         'en' => 'Welcome to your dashboard'],
        'tour.dashboard-overview.step.0.desc'  => ['es' => 'Aquí ves la actividad de tu CP de un vistazo: miembros, reports, adena en circulación y los gráficos de los últimos días.', 'en' => "Here you see your CP's activity at a glance: members, reports, adena in circulation and the last few days' charts."],

        // Tour: profile-characters
        'tour.profile-characters.title'        => ['es' => 'Personajes en el perfil',                           'en' => 'Profile characters'],
        'tour.profile-characters.step.0.title' => ['es' => 'Tu perfil L2',                                      'en' => 'Your L2 profile'],
        'tour.profile-characters.step.0.desc'  => ['es' => 'En esta pantalla guardas tu personaje principal y los secundarios. Cuando reportes loot, podrás indicar con qué personaje farmeaste.', 'en' => 'Here you store your main and alt characters. When you report loot you can tell which one farmed.'],
        'tour.profile-characters.step.1.title' => ['es' => 'Personajes secundarios',                            'en' => 'Secondary characters'],
        'tour.profile-characters.step.1.desc'  => ['es' => 'Añade aquí tus alts. Cada uno con su nick, raza, clase y nivel. Al reportar, el líder los puede elegir.', 'en' => 'Add your alts here. Each one with nick, race, class and level. The leader can pick them in the report modal.'],

        // Tour: loot-pending
        'tour.loot-pending.title'        => ['es' => 'Aprobar loot pendiente',                                  'en' => 'Approve pending loot'],
        'tour.loot-pending.step.0.title' => ['es' => 'Loot pendiente',                                          'en' => 'Pending loot'],
        'tour.loot-pending.step.0.desc'  => ['es' => 'Cuando un miembro reporta un farm/boss, queda como pendiente hasta que tú lo apruebas. Decides el porcentaje al fondo de la CP y los puntos.', 'en' => "When a member reports a farm/boss it sits pending until you approve. You set the % to the CP fund and the points."],
        'tour.loot-pending.step.1.title' => ['es' => 'Pendientes vs historial',                                 'en' => 'Pending vs history'],
        'tour.loot-pending.step.1.desc'  => ['es' => 'Pestaña Pendientes: cosas por revisar. Historial: lo ya confirmado o rechazado. Puedes filtrar y buscar.', 'en' => 'Pending tab: stuff to review. History: confirmed or rejected reports. You can filter and search.'],

        // Tour: party-vault
        'tour.party-vault.title'        => ['es' => 'CP Vault',                                                'en' => 'CP Vault'],
        'tour.party-vault.step.0.title' => ['es' => 'El almacén de la CP',                                      'en' => "The CP's vault"],
        'tour.party-vault.step.0.desc'  => ['es' => 'Aquí vive todo lo que la CP ha conseguido: items, adena, recetas pinneadas, miembros y deudas externas.', 'en' => 'Everything the CP has earned lives here: items, adena, pinned recipes, members and external debts.'],
        'tour.party-vault.step.1.title' => ['es' => 'Pestañas de la CP',                                        'en' => 'CP tabs'],
        'tour.party-vault.step.1.desc'  => ['es' => 'Cada pestaña agrupa una zona: miembros, vault de items, crafting, normas internas y ajustes.', 'en' => 'Each tab covers one area: members, item vault, crafting, internal rules and settings.'],

        // Tour: party-rules
        'tour.party-rules.title'        => ['es' => 'Normas de la CP',                                         'en' => 'CP rules'],
        'tour.party-rules.step.0.title' => ['es' => 'Reglas internas',                                         'en' => 'Internal rules'],
        'tour.party-rules.step.0.desc'  => ['es' => 'En la pestaña Normas el líder puede publicar el reglamento del CP. Cuando hay una versión nueva, todos los miembros tienen que aceptarla antes de seguir.', 'en' => 'In the Rules tab the leader can publish the CP rulebook. When a new version drops, every member has to accept it before continuing.'],

        // Tour: craft-bulk
        'tour.craft-bulk.title'        => ['es' => 'Craft en bloque',                                          'en' => 'Bulk crafting'],
        'tour.craft-bulk.step.0.title' => ['es' => 'Planifica varios crafts a la vez',                         'en' => 'Plan several crafts at once'],
        'tour.craft-bulk.step.0.desc'  => ['es' => 'Añades cuántas unidades de cada receta quieres. El sistema te calcula qué materiales necesitas, cuáles tienes en el vault y cuáles son sub-crafts automáticos.', 'en' => 'You add how many units of each recipe you want. The system computes which materials you need, which you already have in the vault, and which sub-crafts are auto-triggered.'],

        // Tour: admin-cps
        'tour.admin-cps.title'        => ['es' => 'Gestión global de CPs',                                     'en' => 'Global CP management'],
        'tour.admin-cps.step.0.title' => ['es' => 'Todas las CPs',                                             'en' => 'All CPs'],
        'tour.admin-cps.step.0.desc'  => ['es' => 'Aquí ves todos los CPs registrados. Puedes editarlos, impersonar al líder para probar cosas como si fueras él, activarlos/desactivarlos, o borrarlos si quedan vacíos.', 'en' => 'Here you see every registered CP. You can edit them, impersonate the leader to test things as if you were them, toggle active/inactive, or delete the empty ones.'],

        // Tour: admin-users
        'tour.admin-users.title'        => ['es' => 'Gestión global de usuarios',                               'en' => 'Global user management'],
        'tour.admin-users.step.0.title' => ['es' => 'Todos los usuarios',                                       'en' => 'All users'],
        'tour.admin-users.step.0.desc'  => ['es' => 'Lista global de cuentas. Desde aquí cambias roles, reasignas CPs, ajustas adena manualmente, y baneas/desbaneas. El audit log queda por usuario.', 'en' => 'Global account list. From here you change roles, reassign CPs, manually adjust adena, and ban/unban. The audit log is kept per user.'],
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
