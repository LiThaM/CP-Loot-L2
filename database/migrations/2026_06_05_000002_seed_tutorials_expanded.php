<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Full content seed for the expanded /tutoriales page. Each of the
 * 16 topics (8 member-level + 8 leader-only) ships a title, intro
 * paragraph and 4–6 bullet lines, in ES + EN. Plus 3 new tours
 * (party-settings, warehouse-personal, vault-sell) wired to their
 * own titleKey/descKey pairs.
 *
 * All keys are English by convention; values carry the ES/EN pair.
 * Idempotent — re-running just refreshes the rows.
 */
return new class extends Migration
{
    private array $translations = [
        // ---- page-level UI extras ----
        'tutorials.role_intro.member' => [
            'es' => 'Estos son los apartados que tienes a tu disposición como miembro. Cada bloque es una pantalla concreta de la app — expándelo para ver qué hace y, cuando tenga sentido, dispara el tour para verlo en vivo.',
            'en' => 'These are the screens you have access to as a member. Each block is one screen of the app — expand it to see what it does and, when it makes sense, fire the tour to see it live.',
        ],
        'tutorials.role_intro.cp_leader' => [
            'es' => 'Como líder ves todos los apartados de miembro más estos extra. Los temas de miembro siguen aplicando para ti — sólo cambia que tú decides cómo se aprueba el loot, cómo se vende y qué normas hay.',
            'en' => "As a leader you see every member screen plus these extras. The member topics still apply to you — what changes is that you decide how loot is approved, how it's sold and what the rules are.",
        ],

        // =================================================
        //  MEMBER TOPICS (8) — visible to every role
        // =================================================

        // 1. PROFILE
        'tutorials.topic.profile.title' => ['es' => 'Tu perfil',                                                 'en' => 'Your profile'],
        'tutorials.topic.profile.intro' => [
            'es' => 'Tu cuenta y tus personajes de L2 viven en [/profile](/profile). Aquí cambias datos básicos, preferencias y registras los chars con los que farmeas.',
            'en' => "Your account and L2 characters live at [/profile](/profile). It's where you change basic data, preferences, and register the chars you farm with.",
        ],
        'tutorials.topic.profile.bullet.0' => ['es' => '**Cuenta**: cambia nombre visible, email y contraseña. El email se usa para los avisos del bot.',                                          'en' => '**Account**: change your display name, email and password. The email is used for bot notifications.'],
        'tutorials.topic.profile.bullet.1' => ['es' => '**Preferencias**: idioma ES/EN (afecta a toda la web) y tema oscuro/claro (se persiste en tu navegador).',                                'en' => '**Preferences**: language ES/EN (affects the whole site) and dark/light theme (persists in your browser).'],
        'tutorials.topic.profile.bullet.2' => ['es' => '**Personaje principal**: tu char actual con nick, raza (Human/Elf/Dark Elf/Orc/Dwarf/Kamael), clase (catálogo Interlude completo) y nivel opcional.', 'en' => '**Main character**: your current char with nick, race (Human/Elf/Dark Elf/Orc/Dwarf/Kamael), class (full Interlude catalogue) and optional level.'],
        'tutorials.topic.profile.bullet.3' => ['es' => '**Alts**: añade todos los personajes secundarios que quieras. Cada uno con su nick, raza, clase, nivel.',                                'en' => '**Alts**: add as many secondary characters as you want. Each with their own nick, race, class, level.'],
        'tutorials.topic.profile.bullet.4' => ['es' => '**Para qué sirven los alts**: cuando reportes loot, el líder podrá elegir con qué char tuyo farmeaste esa sesión. Útil si saltas entre main y dual-box.', 'en' => '**Why alts matter**: when you report loot, the leader can pick which of your chars farmed that session. Handy if you swap between your main and a dual-box.'],
        'tutorials.topic.profile.bullet.5' => ['es' => '**Las clases dependen de la raza**: al seleccionar raza, el dropdown de clases sólo muestra las compatibles con esa raza.',              'en' => '**Classes are race-gated**: picking a race filters the class dropdown to the compatible options.'],

        // 2. DASHBOARD
        'tutorials.topic.dashboard.title' => ['es' => 'El Dashboard',                                            'en' => 'The Dashboard'],
        'tutorials.topic.dashboard.intro' => [
            'es' => 'La pantalla principal después de hacer login en [/dashboard](/dashboard). Pensada para que veas tu situación dentro de la CP de un vistazo.',
            'en' => 'The main screen after login at [/dashboard](/dashboard). Designed to give you your standing inside the CP at a glance.',
        ],
        'tutorials.topic.dashboard.bullet.0' => ['es' => '**KPIs en vivo**: cuántos miembros activos hay, total de loot reports confirmados y adena que la CP ha movido.',                       'en' => '**Live KPIs**: how many active members, total confirmed loot reports and the adena moved by the CP.'],
        'tutorials.topic.dashboard.bullet.1' => ['es' => '**Tu balance**: puntos DKP acumulados y adena ganada vs cobrada. Si te queda saldo pendiente, lo ves aquí.',                            'en' => '**Your balance**: DKP points and adena earned vs collected. If there is pending payout, you see it here.'],
        'tutorials.topic.dashboard.bullet.2' => ['es' => '**Gráfico de actividad**: línea de los últimos 14 días con visitas y loot reports. Buen indicador de cuándo "está la cosa activa".',  'en' => '**Activity chart**: 14-day line with visits and loot reports. Good indicator of when "things are happening".'],
        'tutorials.topic.dashboard.bullet.3' => ['es' => '**Atajos al pendiente**: si hay loot por aprobar (para líderes) o normas nuevas por aceptar, te lo recuerda con un badge pulsante.',  'en' => '**Pending shortcuts**: if there is loot to approve (for leaders) or new rules to accept, a pulsing badge reminds you.'],
        'tutorials.topic.dashboard.bullet.4' => ['es' => '**Cambio de idioma y tema**: arriba a la derecha en la nav, sin tener que ir al perfil.',                                              'en' => '**Language / theme switcher**: top right in the nav, no need to go into your profile.'],

        // 3. REPORT LOOT
        'tutorials.topic.report_loot.title' => ['es' => 'Reportar loot',                                         'en' => 'Reporting loot'],
        'tutorials.topic.report_loot.intro' => [
            'es' => 'Cualquier miembro puede abrir un report de un farm, boss, epic, siege o crafting. El report queda pendiente hasta que el líder lo apruebe (o rechace).',
            'en' => 'Any member can open a report for a farm, boss, epic, siege or crafting session. The report sits pending until the leader approves (or rejects) it.',
        ],
        'tutorials.topic.report_loot.bullet.0' => ['es' => '**Tipos de evento**: FARM (party rutinaria), BOSS (raid bosses), EPIC (Antharas/Valakas/Baium…), SIEGE (castle/clan hall), RETURN (un item que no era para nosotros y se devuelve), WAREHOUSE_CRAFT (crafteo desde el almacén).', 'en' => '**Event types**: FARM (routine party), BOSS (raid bosses), EPIC (Antharas/Valakas/Baium…), SIEGE (castle/clan hall), RETURN (an item that was not ours, being returned), WAREHOUSE_CRAFT (crafting from the vault).'],
        'tutorials.topic.report_loot.bullet.1' => ['es' => '**Attendees**: añade a los miembros que estuvieron. Si alguien externo participó, lo metes como external (escribes su nick a mano). Cada attendee puede asociarse a un char específico tuyo (main/alt).', 'en' => '**Attendees**: add the members who were there. Anyone external (non-CP) goes in as external with their L2 nick. Each attendee can be tied to a specific char of theirs (main/alt).'],
        'tutorials.topic.report_loot.bullet.2' => ['es' => '**Items y adena**: lista cada item del drop (búsqueda con autocomplete) con la cantidad. La adena va aparte como entry tipo Adena.',  'en' => '**Items and adena**: list every dropped item (autocomplete search) with quantity. Adena is a separate entry of type Adena.'],
        'tutorials.topic.report_loot.bullet.3' => ['es' => '**Captura obligatoria**: por defecto el CP exige screenshot para validar el report. El líder lo puede desactivar en Ajustes si confía en ti.',  'en' => '**Screenshot required**: by default the CP enforces a screenshot to validate the report. The leader can disable it in Settings if trust is high.'],
        'tutorials.topic.report_loot.bullet.4' => ['es' => '**Cp_share_pct slider**: % de la adena que se queda en el fondo común. El resto se reparte entre attendees. Presets 0/10/20/50/100% o número libre.', 'en' => '**Cp_share_pct slider**: % of adena that stays in the CP fund. The remainder splits among attendees. Presets 0/10/20/50/100% or free entry.'],
        'tutorials.topic.report_loot.bullet.5' => ['es' => '**Qué pasa después**: queda como "pendiente" en /loot, el líder ve la pulse-badge y aprueba/rechaza con su propio % al fondo.',     'en' => '**What happens next**: it goes into "pending" in /loot, the leader sees the pulse-badge and approves/rejects with their own % to the fund.'],

        // 4. CP VAULT
        'tutorials.topic.cp_vault.title' => ['es' => 'CP Vault — el almacén común',                              'en' => 'CP Vault — the shared stash'],
        'tutorials.topic.cp_vault.intro' => [
            'es' => 'Pestaña "Warehouse CP" de [/party](/party). Es el inventario compartido de la CP: items, adena del fondo y todo lo que está disponible para crafting o venta.',
            'en' => '"Warehouse CP" tab in [/party](/party). The shared inventory of the CP: items, fund adena and everything available for crafting or selling.',
        ],
        'tutorials.topic.cp_vault.bullet.0' => ['es' => '**Modo cards vs lista**: toggle arriba a la derecha. Cards muestra cada item con icono grande; lista los comprime en una tabla. El modo se guarda por usuario.',  'en' => '**Cards vs list view**: toggle at the top right. Cards shows each item with a big icon; list compresses them in a table. View preference is per-user.'],
        'tutorials.topic.cp_vault.bullet.1' => ['es' => '**Filtros**: búsqueda por nombre, filtro por categoría (Weapon, Armor, Recipe, Material…) y por grade (S, A, B, C…). Ordena por más recientes o más antiguos.',  'en' => '**Filters**: search by name, filter by category (Weapon, Armor, Recipe, Material…) and by grade (S, A, B, C…). Order by newest or oldest.'],
        'tutorials.topic.cp_vault.bullet.2' => ['es' => '**Valor del stock**: si los items tienen precio de mercado, el banner arriba te da el valor total estimado del vault en adena.',                          'en' => '**Stock value**: if items have a market price set, the banner up top shows the total estimated vault value in adena.'],
        'tutorials.topic.cp_vault.bullet.3' => ['es' => '**Origen de cada item**: al expandir una entrada ves de qué farm/sesión vino, qué report la trajo y quién la reportó. Tracking real.',                    'en' => '**Each item origin**: expanding an entry tells you which farm/session brought it, which report carried it and who reported it. Real audit trail.'],
        'tutorials.topic.cp_vault.bullet.4' => ['es' => '**Adena del fondo**: la barra superior separa el adena bruto del fondo y lo que aún se debe a miembros — el "fondo neto" es el que la CP realmente tiene para gastar.', 'en' => '**Fund adena**: the top bar separates the raw fund balance from what is still owed to members — the "net fund" is what the CP can actually spend.'],

        // 5. PERSONAL WAREHOUSE
        'tutorials.topic.personal_warehouse.title' => ['es' => 'Tu warehouse personal y deudas',                 'en' => 'Your personal warehouse and balance'],
        'tutorials.topic.personal_warehouse.intro' => [
            'es' => 'Tu vista privada en [/warehouse](/warehouse). Muestra qué te ha asignado el CP y qué adena te debe (o ya te ha pagado).',
            'en' => 'Your private view at [/warehouse](/warehouse). Shows what the CP has assigned to you and what adena it still owes (or has already paid).',
        ],
        'tutorials.topic.personal_warehouse.bullet.0' => ['es' => '**Items asignados**: cada vez que el líder hace un ASSIGN o un drop te toca a ti, aparece aquí. Útil para confirmar que recibiste lo que tocaba.',         'en' => '**Assigned items**: every time the leader does an ASSIGN or a drop is allocated to you, it shows up here. Handy to confirm you got what you should.'],
        'tutorials.topic.personal_warehouse.bullet.1' => ['es' => '**Adena pendiente**: lo que el CP te debe del reparto de farms (mode "attendees") o de ventas pagadas a attendees.',                                       'en' => '**Pending adena**: what the CP owes you from farm splits (mode "attendees") or from sales paid out to attendees.'],
        'tutorials.topic.personal_warehouse.bullet.2' => ['es' => '**Adena cobrada**: histórico de payouts ya recibidos. Cada uno cita el report origen para que puedas auditarlo.',                                          'en' => '**Paid-out adena**: history of payouts you have already received. Each one cites the originating report so you can audit it.'],
        'tutorials.topic.personal_warehouse.bullet.3' => ['es' => '**Verificar una venta específica**: si el líder vende un item de un farm en el que estabas, te llega tu porción aquí. Cruza la fecha del sell con la fila para confirmar.', 'en' => '**Verifying a specific sale**: if the leader sells an item from a farm you were in, your share lands here. Cross the sale date with the row to confirm.'],
        'tutorials.topic.personal_warehouse.bullet.4' => ['es' => '**Externos**: si participaste como externo en otro CP, la deuda hacia ti vive en *su* /system/external-payouts, no aquí.',                                  'en' => '**Externals**: if you joined another CP as external, the debt towards you lives in *their* /system/external-payouts, not here.'],

        // 6. CRAFTING
        'tutorials.topic.crafting.title' => ['es' => 'Crafting y recetas',                                       'en' => 'Crafting and recipes'],
        'tutorials.topic.crafting.intro' => [
            'es' => 'Hay dos cosas: un explorador público del catálogo de recetas en [/recipes](/recipes) y la pestaña "Crafting" dentro de [/party](/party) con las pinneadas por el líder.',
            'en' => 'Two things to know: a public catalogue browser at [/recipes](/recipes) and the "Crafting" tab inside [/party](/party) with leader-pinned recipes.',
        ],
        'tutorials.topic.crafting.bullet.0' => ['es' => '**Explorador público**: cualquiera (con o sin login) puede ver árboles de crafteo, materiales y outputs. Filtros por chronicle y por grade.',                'en' => '**Public explorer**: anyone (login or not) can see crafting trees, materials and outputs. Filters by chronicle and grade.'],
        'tutorials.topic.crafting.bullet.1' => ['es' => '**Recetas pinneadas**: el líder selecciona las recetas relevantes para tu CP. Aparecen en /party con orden de prioridad.',                                  'en' => '**Pinned recipes**: the leader curates the recipes relevant to your CP. They show up in /party in priority order.'],
        'tutorials.topic.crafting.bullet.2' => ['es' => '**Auto-craft de materiales intermedios**: si te falta p.ej. Crafted Leather pero tienes los raws, al craftear el item final el sistema te lo produce solo. Te dice qué hizo en el toast.', 'en' => '**Auto sub-craft**: if you are missing e.g. Crafted Leather but you have the raws, when you craft the final item the system produces it for you. The toast tells you what was auto-crafted.'],
        'tutorials.topic.crafting.bullet.3' => ['es' => '**Badge "Can craft"**: si tienes sub-materiales en el warehouse, los items intermedios muestran cuántas unidades podrías producir ahora mismo.',              'en' => '**"Can craft" badge**: if you have sub-materials in the warehouse, intermediate items show how many units you could produce right now.'],
        'tutorials.topic.crafting.bullet.4' => ['es' => '**Precio estimado**: cada receta calcula coste de materiales + fee y lo muestra. Útil para decidir entre craftear vs comprar.',                              'en' => '**Estimated price**: every recipe computes material cost + fee and shows it. Helps you decide craft vs buy.'],
        'tutorials.topic.crafting.bullet.5' => ['es' => '**Scrolls (recipe items)**: para weapons/armors/jewelry el sistema exige el "Recipe: X" en el warehouse; para materials intermedios (Cord, Leather…) no.',  'en' => '**Recipe scrolls**: for weapons/armors/jewelry the system requires the "Recipe: X" scroll in the warehouse; for intermediate materials (Cord, Leather…) it does not.'],

        // 7. CP RULES
        'tutorials.topic.rules.title' => ['es' => 'Normas de la CP',                                              'en' => 'CP rules'],
        'tutorials.topic.rules.intro' => [
            'es' => 'Cada CP puede publicar su reglamento interno en la pestaña "Normas" de [/party](/party). Es un documento único, versionado: cuando el líder lo edita, todos los miembros tienen que aceptarlo de nuevo.',
            'en' => 'Each CP can publish its internal rulebook in the "Rules" tab of [/party](/party). It is a single versioned document: when the leader edits it, every member has to accept it again.',
        ],
        'tutorials.topic.rules.bullet.0' => ['es' => '**Acceso**: cualquier miembro puede leer las normas. Sólo el líder fundador puede editarlas.',                                              'en' => '**Access**: any member can read the rules. Only the founder leader can edit them.'],
        'tutorials.topic.rules.bullet.1' => ['es' => '**Versión vs aceptación**: cada documento tiene un número de versión. Tu propio "accepted_version" indica qué versión has aceptado ya.', 'en' => '**Version vs acceptance**: every doc has a version number. Your own "accepted_version" tracks which version you have already accepted.'],
        'tutorials.topic.rules.bullet.2' => ['es' => '**Modal bloqueante**: si tu accepted_version es menor que la actual, te sale un modal sin botón cerrar al entrar. Sólo "Acepto las normas" lo cierra.',     'en' => '**Blocking modal**: if your accepted_version is below the current one, you get a modal on login with no close button. Only "I accept the rules" dismisses it.'],
        'tutorials.topic.rules.bullet.3' => ['es' => '**Sin tracking de impersonación**: si un admin impersona a otro miembro, la aceptación del admin NO bumpea la versión del miembro. Tu propia aceptación queda intacta.',  'en' => '**No impersonation bleed**: if an admin impersonates another member, the admin clicking accept does NOT bump that member version. Your own acceptance stays intact.'],
        'tutorials.topic.rules.bullet.4' => ['es' => '**Markdown limitado**: el body soporta `**negrita**`, *italic*, ``código``, y `[enlaces](url)` para apuntar a otras pantallas. Listas las puede formatear el líder con saltos de línea.',  'en' => '**Light markdown**: the body supports `**bold**`, *italic*, ``code``, and `[links](url)` pointing to other screens. The leader formats lists with line breaks.'],

        // 8. MISC: WISHLIST + TICKETS + CHANGELOG
        'tutorials.topic.misc_member.title' => ['es' => 'Wishlist, tickets y changelog',                          'en' => 'Wishlist, tickets and changelog'],
        'tutorials.topic.misc_member.intro' => [
            'es' => 'Tres apartados menores pero útiles: pedir items que quieres, abrir tickets de soporte y ver qué hemos cambiado en la web.',
            'en' => 'Three minor but useful corners: ask for items you want, open support tickets and check what we shipped on the web.',
        ],
        'tutorials.topic.misc_member.bullet.0' => ['es' => '**Wishlist**: en la pestaña "Wishlist" de /party puedes anotar items que te interesan. El líder ve la lista al repartir y puede priorizarte.',                          'en' => '**Wishlist**: in the "Wishlist" tab of /party you can flag items you want. The leader sees the list when allocating and can prioritise you.'],
        'tutorials.topic.misc_member.bullet.1' => ['es' => '**Tickets**: ([/tickets](/tickets)) — abrir incidencia técnica o reportar un bug. Adjuntas imagen/video si toca. Los líderes y admins ven los tickets.',                  'en' => '**Tickets**: ([/tickets](/tickets)) — open a technical issue or bug report. Attach image / video if needed. Leaders and admins see tickets.'],
        'tutorials.topic.misc_member.bullet.2' => ['es' => '**Changelog**: ([/changelog](/changelog)) y modal de aviso cuando se publican entradas nuevas. Cuando lo aceptas, el modal no vuelve a salir hasta que haya algo nuevo.', 'en' => '**Changelog**: ([/changelog](/changelog)) and a notice modal when new entries ship. Once you acknowledge, it stays quiet until something new is published.'],
        'tutorials.topic.misc_member.bullet.3' => ['es' => '**Dropdown del usuario**: arriba a la derecha tienes acceso rápido a Perfil, Personajes, Tutoriales, Changelog y donaciones.',                                            'en' => '**User dropdown**: top right gives quick access to Profile, Characters, Tutorials, Changelog and donations.'],

        // =====================================================
        //  CP_LEADER TOPICS (8 extra) — on top of member ones
        // =====================================================

        // 9. APPROVE / REJECT LOOT
        'tutorials.topic.approve_loot.title' => ['es' => 'Aprobar o rechazar loot',                              'en' => 'Approving or rejecting loot'],
        'tutorials.topic.approve_loot.intro' => [
            'es' => 'Los reports pendientes esperan tu decisión en [/loot](/loot) → pestaña "Pendientes". Apruebas, rechazas o (si ya estaba confirmado) lo anulas más tarde.',
            'en' => "Pending reports wait for your call in [/loot](/loot) → 'Pending' tab. You approve, reject or (if already confirmed) void it later.",
        ],
        'tutorials.topic.approve_loot.bullet.0' => ['es' => '**Modal de aprobación**: editas attendees, ajustas el cp_share_pct, fijas los puntos por evento y confirmas. La adena se reparte automáticamente según el slider.',  'en' => '**Approval modal**: edit attendees, tweak cp_share_pct, set the event points and confirm. Adena splits automatically per the slider.'],
        'tutorials.topic.approve_loot.bullet.1' => ['es' => '**Quick approve para RETURN**: si el report es de tipo RETURN (item devuelto), tienes botones Accept return / Reject return sin abrir modal completo.',                'en' => '**Quick approve for RETURN**: if the report is a RETURN (item being returned), Accept return / Reject return work without opening the full modal.'],
        'tutorials.topic.approve_loot.bullet.2' => ['es' => '**Anular un report ya confirmado**: botón "Void" en la fila del historial. Marca el report como anulado, revierte stock y adena. Reversible (basta limpiar voided_at).',  'en' => '**Voiding a confirmed report**: "Void" button on the history row. Flags the report as voided and reverts stock and adena. Reversible (just clear voided_at).'],
        'tutorials.topic.approve_loot.bullet.3' => ['es' => '**Attendees externos**: puedes meter gente que no está en la CP por su nick. La adena que les toque queda en /system/external-payouts para liquidar después.',          'en' => '**External attendees**: you can add people who are not in the CP by their nick. The adena owed to them lives in /system/external-payouts until you settle.'],
        'tutorials.topic.approve_loot.bullet.4' => ['es' => '**El % al fondo manda**: el slider final que tú pones gana al que el miembro había propuesto. Tú decides cuánto se queda en el fondo y cuánto se reparte.',           'en' => '**Your % wins**: the final slider you set overrides whatever the member proposed. You decide how much stays in the fund vs gets split.'],

        // 10. VAULT SELL
        'tutorials.topic.vault_sell.title' => ['es' => 'Vender items del vault',                                 'en' => 'Selling items from the vault'],
        'tutorials.topic.vault_sell.intro' => [
            'es' => 'Botón "Sell" sobre un item del CP Vault. Si el item está repartido entre varios farms, el modal te lo divide solo en orden FIFO y crea una venta por farm origen.',
            'en' => '"Sell" button on a CP Vault item. If the item lives across several farms, the modal auto-splits it FIFO and creates one sale per source farm.',
        ],
        'tutorials.topic.vault_sell.bullet.0' => ['es' => '**Auto FIFO**: por defecto se vende empezando por el farm más antiguo. Útil para que la adena vieja se libere antes que la nueva.',                            'en' => '**Auto FIFO**: by default it sells starting from the oldest farm. Helps keep old adena flowing out before new.'],
        'tutorials.topic.vault_sell.bullet.1' => ['es' => '**Cp_share_pct por farm**: cada venta respeta el porcentaje al fondo del farm origen, no se mezclan. Si pusiste 50% en un farm y 100% en otro, se aplica por separado.',  'en' => '**Per-farm cp_share_pct**: each sale honours the CP-fund % of its source farm — they do not blend. 50% on one farm and 100% on another are applied separately.'],
        'tutorials.topic.vault_sell.bullet.2' => ['es' => '**Desglose previo**: antes de confirmar ves "X uds del farm #Y · CP Z% · attendees: A,B,C · payout cada uno". Sin sorpresas.',                                  'en' => '**Preview breakdown**: before confirming you see "X units from farm #Y · CP Z% · attendees: A,B,C · payout each". No surprises.'],
        'tutorials.topic.vault_sell.bullet.3' => ['es' => '**Override manual**: botón "Pick a specific farm" si quieres saltarte el FIFO y vender desde un farm concreto. Vuelves al flujo de venta clásico.',           'en' => '**Manual override**: "Pick a specific farm" button if you want to skip the FIFO and sell from a specific source. Falls back to the classic single-source flow.'],
        'tutorials.topic.vault_sell.bullet.4' => ['es' => '**Bloqueos**: si un farm involucrado no tiene attendees y su cp_share_pct < 100, se bloquea con un aviso pidiendo venderlo aparte con 100% al CP.',           'en' => '**Guards**: if a source farm has no attendees and its cp_share_pct < 100, the modal blocks with a warning asking to sell that one separately at 100% CP share.'],

        // 11. ADD STOCK / BUY STOCK / RECHECK
        'tutorials.topic.add_buy_recheck.title' => ['es' => 'Add stock, Buy stock y Recheck',                    'en' => 'Add stock, Buy stock and Recheck'],
        'tutorials.topic.add_buy_recheck.intro' => [
            'es' => 'Tres acciones para "meter mano" al vault sin pasar por un report normal. Útiles para arreglar desfases entre la BD y la realidad.',
            'en' => 'Three ways to "patch" the vault without going through a normal report. Handy to reconcile DB with reality.',
        ],
        'tutorials.topic.add_buy_recheck.bullet.0' => ['es' => '**Add stock**: items que llegaron sin report (regalos, drops fuera de la app). Sólo admin global, no líderes. Quedan en el vault con origen "manual_admin".',                                          'en' => '**Add stock**: items that arrived without a report (gifts, off-app drops). Admin-only, not leaders. They land in the vault with origin "manual_admin".'],
        'tutorials.topic.add_buy_recheck.bullet.1' => ['es' => '**Buy stock**: registras una compra con coste en adena + attendees que aportaron. La adena sale del fondo, se asigna a quien pagó, y el item aparece en el vault con origin "buy".',                'en' => '**Buy stock**: log a purchase with adena cost + attendees who contributed. Adena leaves the fund, gets credited to the contributors, and the item lands in the vault with origin "buy".'],
        'tutorials.topic.add_buy_recheck.bullet.2' => ['es' => '**Recheck**: para resincronizar el warehouse físico in-game con la BD. Eliges items, ves el stock que dice la app y escribes el real. Sólo se crean ajustes para deltas ≠ 0.',                       'en' => '**Recheck**: to resync the in-game warehouse with the DB. Pick items, see the stock the app says and type the actual one. Adjustments are created only for deltas ≠ 0.'],
        'tutorials.topic.add_buy_recheck.bullet.3' => ['es' => '**Gain vs Loss**: el recheck separa ganancias (más de lo que decía la BD → ADMIN_ADJUST_IN) y pérdidas (menos → ADMIN_ADJUST_OUT) en reports distintos. No tienes que registrar N ADDs manuales.', 'en' => '**Gain vs Loss**: recheck splits gains (more than the DB said → ADMIN_ADJUST_IN) and losses (fewer → ADMIN_ADJUST_OUT) into separate reports. No need to log N manual ADDs.'],
        'tutorials.topic.add_buy_recheck.bullet.4' => ['es' => '**Trazabilidad**: cualquier add/buy/recheck queda como un report normal en /loot. Cualquier miembro puede auditarlo.',                                                                                  'en' => '**Audit trail**: every add/buy/recheck is logged as a normal report in /loot. Any member can audit it.'],

        // 12. EDIT RULES
        'tutorials.topic.edit_rules.title' => ['es' => 'Editar las normas de la CP',                              'en' => 'Editing the CP rules'],
        'tutorials.topic.edit_rules.intro' => [
            'es' => 'Botón "Editar normas" en la pestaña "Normas" de /party. Cada save sube la versión y obliga a todos los miembros a re-aceptar.',
            'en' => '"Edit rules" button in the "Rules" tab of /party. Every save bumps the version and forces every member to re-accept.',
        ],
        'tutorials.topic.edit_rules.bullet.0' => ['es' => '**Confirmación previa**: antes de publicar te sale un swal "Cada miembro tendrá que aceptar las normas de nuevo. ¿Continuar?". Evita publicar erratas accidentales.',  'en' => '**Confirm prompt**: before publishing you get a swal saying "Every member will have to accept the rules again. Continue?". Avoids accidental typo publishes.'],
        'tutorials.topic.edit_rules.bullet.1' => ['es' => '**Auto-aceptación del líder**: al guardar, tu propia accepted_version sube automáticamente. No te sale el modal a ti.',                                                'en' => '**Leader auto-accept**: when you save, your own accepted_version bumps automatically. The modal does not fire for you.'],
        'tutorials.topic.edit_rules.bullet.2' => ['es' => '**Body limit**: hasta 20.000 caracteres. Soporta `**negrita**`, *italic*, `código` y `[enlaces](/loot)` clicables hacia otras pantallas.',                              'en' => '**Body limit**: up to 20,000 chars. Supports `**bold**`, *italic*, `code` and clickable `[links](/loot)` pointing to other screens.'],
        'tutorials.topic.edit_rules.bullet.3' => ['es' => '**Versión incremental**: la primera publicación queda como v1; cada edición posterior es v2, v3, etc. El campo `updated_by` registra qué líder hizo el cambio.',        'en' => '**Incremental version**: the first publish is v1; every later edit is v2, v3, etc. `updated_by` records which leader made the change.'],
        'tutorials.topic.edit_rules.bullet.4' => ['es' => '**Cuándo NO editar**: si el cambio es trivial (typo, formateo), evita bumpear si puedes. Cada bump obliga a re-aceptar a todos.',                                       'en' => '**When NOT to edit**: if the change is trivial (typo, formatting), skip if you can. Every bump forces everyone to re-accept.'],

        // 13. POINTS CONFIG
        'tutorials.topic.points_config.title' => ['es' => 'Configurar puntos por evento',                         'en' => 'Configuring event points'],
        'tutorials.topic.points_config.intro' => [
            'es' => 'Pestaña "Configuración" de /party. Aquí decides cuántos puntos DKP valen cada tipo de evento, y puedes resetear todos los puntos de los miembros.',
            'en' => '"Configuration" tab in /party. Here you decide how many DKP points each event type is worth, and you can reset every member balance.',
        ],
        'tutorials.topic.points_config.bullet.0' => ['es' => '**Puntos por evento**: define cuántos puntos da una sesión FARM, BOSS, EPIC o SIEGE. Los miembros ven los puntos en su perfil y en el dashboard.',  'en' => '**Per-event points**: define how many points a FARM, BOSS, EPIC or SIEGE session awards. Members see their points in profile and dashboard.'],
        'tutorials.topic.points_config.bullet.1' => ['es' => '**Cambio en caliente**: si subes los puntos de BOSS, los próximos reports usarán el nuevo valor. No afecta a reports ya confirmados.',              'en' => '**Hot change**: if you bump BOSS points, future reports use the new value. Already-confirmed reports keep theirs.'],
        'tutorials.topic.points_config.bullet.2' => ['es' => '**Reset DKP**: botón "Reset DKP points" con confirmación swal. Pone a 0 los puntos de todos los miembros. La adena NO se ve afectada.',              'en' => '**Reset DKP**: "Reset DKP points" button with swal confirmation. Sets every member to 0 points. Adena is NOT affected.'],
        'tutorials.topic.points_config.bullet.3' => ['es' => '**Cuándo resetear**: típicamente cuando arrancáis una nueva "season" o decidisteis cambiar reglas de reparto. El reset es brusco — avisa al CP antes.',  'en' => '**When to reset**: typically when you start a new "season" or change reward rules. The reset is abrupt — warn the CP first.'],
        'tutorials.topic.points_config.bullet.4' => ['es' => '**Sólo el founder**: el líder fundador es el único que puede resetear. Co-líderes pueden ajustar los puntos por evento pero no resetear.',           'en' => '**Founder only**: only the founder leader can reset. Co-leaders can adjust event points but not reset.'],

        // 14. CP SETTINGS
        'tutorials.topic.cp_settings.title' => ['es' => 'Ajustes del CP',                                         'en' => 'CP settings'],
        'tutorials.topic.cp_settings.intro' => [
            'es' => 'Pestaña "Ajustes" de /party. Datos básicos del CP (nombre, server, logo) y comportamientos por defecto que afectan a toda la party.',
            'en' => '"Settings" tab in /party. Basic CP info (name, server, logo) and default behaviours that affect the whole party.',
        ],
        'tutorials.topic.cp_settings.bullet.0' => ['es' => '**Logo**: sube una imagen del clan/cp. Aparece en el navbar, el dashboard y la lista de CPs. Recomendado < 3MB.',                                                                                'en' => '**Logo**: upload a clan/CP image. Shows up in the navbar, dashboard and CP list. Recommended < 3MB.'],
        'tutorials.topic.cp_settings.bullet.1' => ['es' => '**Nombre y server**: edita el nombre visible y el server donde juega tu CP. Útil si os movéis o renombráis la party.',                                                                          'en' => '**Name and server**: edit the visible CP name and the server you play on. Useful if you move servers or rename the party.'],
        'tutorials.topic.cp_settings.bullet.2' => ['es' => '**Chronicle locked**: la chronicle (LU4, IL, C5…) no se cambia. Se eligió al crear el CP y filtra todo el catálogo de items y recetas.',                                                          'en' => '**Chronicle locked**: chronicle (LU4, IL, C5…) cannot be changed. It was set when the CP was created and gates the item / recipe catalogue.'],
        'tutorials.topic.cp_settings.bullet.3' => ['es' => '**Captura obligatoria**: checkbox que decide si los reports exigen screenshot o no. Por defecto activado. Si confías mucho en la gente, lo apagas y los reports van más rápido.',               'en' => '**Screenshot required**: checkbox that decides whether reports demand a screenshot or not. On by default. If trust is high, turn it off and reports become quicker.'],
        'tutorials.topic.cp_settings.bullet.4' => ['es' => '**Invite link**: botón "Copiar enlace de invitación" — link único con un código que permite a usuarios nuevos registrarse directamente como miembros pending de tu CP.',                          'en' => '**Invite link**: "Copy invite link" button — a unique URL with a code that lets new users register straight as pending members of your CP.'],

        // 15. MEMBERS MGMT
        'tutorials.topic.members_mgmt.title' => ['es' => 'Gestión de miembros',                                   'en' => 'Member management'],
        'tutorials.topic.members_mgmt.intro' => [
            'es' => 'Pestaña "Miembros" de /party. Aprobar nuevos, ajustar saldos de adena/puntos a mano, cambiar roles, banear/desbanear.',
            'en' => '"Members" tab in /party. Approve new ones, manually adjust adena / points balances, change roles, ban / unban.',
        ],
        'tutorials.topic.members_mgmt.bullet.0' => ['es' => '**Pending**: usuarios que se han registrado con el invite link aún sin aprobar. Botón "Aprobar" / "Rechazar" en cada fila.',                                                                          'en' => '**Pending**: users who signed up with the invite link but are not yet approved. "Approve" / "Reject" button on each row.'],
        'tutorials.topic.members_mgmt.bullet.1' => ['es' => '**Ajuste manual de adena**: ✏️ junto al saldo de un miembro. Sube un modal con cantidad + descripción + screenshot opcional. Va al audit log como ADMIN_ADJUST_IN o OUT según signo.',                  'en' => '**Manual adena adjust**: ✏️ next to a member balance. Modal with amount + description + optional screenshot. Logged as ADMIN_ADJUST_IN or OUT depending on sign.'],
        'tutorials.topic.members_mgmt.bullet.2' => ['es' => '**Roles internos**: cambia el rol de un miembro (member / accountant / cp_leader). El founder no puede ser degradado desde aquí — para eso necesitas /system/users (admin global).',                   'en' => '**Internal roles**: change a member role (member / accountant / cp_leader). The founder cannot be demoted here — for that you need /system/users (global admin).'],
        'tutorials.topic.members_mgmt.bullet.3' => ['es' => '**Ban/Unban**: marca un miembro como banned. Sigue en el listado pero queda sin acceso. Reversible con Unban.',                                                                                          'en' => '**Ban / Unban**: flag a member as banned. They stay in the list but lose access. Reversible with Unban.'],
        'tutorials.topic.members_mgmt.bullet.4' => ['es' => '**Audit por usuario**: clickeas en el nombre de un miembro y ves su histórico de cambios — roles, ajustes de adena, bans, etc. Cada entrada lleva quién y cuándo.',                                     'en' => '**Per-user audit**: click on a member name to see their change history — roles, adena adjustments, bans, etc. Each entry carries who and when.'],
        'tutorials.topic.members_mgmt.bullet.5' => ['es' => '**Founder único**: el founder leader (campo `leader_id` del CP) es único. Si necesitas pasarle el founder a otro miembro, el admin global lo hace desde /system/cps.',                                  'en' => '**Single founder**: the founder leader (the CP `leader_id` column) is unique. To hand it over to another member, the global admin does it from /system/cps.'],

        // 16. CRAFT BULK + EXTERNAL PAYOUTS
        'tutorials.topic.craft_bulk_external.title' => ['es' => 'Craft Bulk planner y pagos externos',            'en' => 'Craft Bulk planner and external payouts'],
        'tutorials.topic.craft_bulk_external.intro' => [
            'es' => 'Dos herramientas avanzadas: planificar muchos crafts a la vez con descuento real del vault, y gestionar adena que debes a non-CP players.',
            'en' => 'Two advanced tools: plan many crafts at once with real vault deduction, and manage adena owed to non-CP players.',
        ],
        'tutorials.topic.craft_bulk_external.bullet.0' => ['es' => '**Craft Bulk** ([/party/craft-bulk](/party/craft-bulk)): añades varias recetas con cantidades. El sistema agrega materiales y los cruza con tu vault. Te dice qué tienes y qué te falta.',  'en' => '**Craft Bulk** ([/party/craft-bulk](/party/craft-bulk)): add several recipes with quantities. The system aggregates materials and crosses them against your vault. Tells you what you have and what is missing.'],
        'tutorials.topic.craft_bulk_external.bullet.1' => ['es' => '**Sub-crafts auto**: si te faltan materiales intermedios craftables (p.ej. Crafted Leather), el planner los desglosa y te dice exactamente cuántos craftear de cada uno.',                       'en' => '**Auto sub-crafts**: if you are missing craftable intermediates (e.g. Crafted Leather), the planner breaks them down and tells you exactly how many of each to craft.'],
        'tutorials.topic.craft_bulk_external.bullet.2' => ['es' => '**Read-only**: el planner sólo calcula — no consume el vault ni crea reports. Es una herramienta de planificación. Tu vault real no se toca hasta que craftees a mano.',                       'en' => '**Read-only**: the planner only computes — it does not touch the vault or create reports. It is a planning tool. Your real vault is untouched until you craft for real.'],
        'tutorials.topic.craft_bulk_external.bullet.3' => ['es' => '**External payouts** ([/system/external-payouts](/system/external-payouts)): listado de externos a los que el CP debe adena. Filtros pending / paid / all.',                                    'en' => '**External payouts** ([/system/external-payouts](/system/external-payouts)): list of externals the CP owes adena to. Filters pending / paid / all.'],
        'tutorials.topic.craft_bulk_external.bullet.4' => ['es' => '**Marcar como pagado**: cuando le pasas la adena al externo in-game, vuelves aquí y pulsas "Mark paid". Queda registrado con fecha. Útil para auditoría.',                                       'en' => '**Mark as paid**: once you hand the adena to the external in-game, you come back here and click "Mark paid". Logged with date. Useful for audit.'],
        'tutorials.topic.craft_bulk_external.bullet.5' => ['es' => '**Quién paga**: el líder fundador y los co-líderes pueden marcar pagado. Cualquier miembro lo puede ver en modo solo lectura, para transparencia.',                                              'en' => '**Who pays**: the founder leader and co-leaders can mark paid. Any member can see it in read-only mode for transparency.'],

        // =====================================================
        //  NEW TOUR STRINGS (3 nuevos tours)
        // =====================================================

        // party-settings tour
        'tour.party-settings.title' => ['es' => 'Ajustes del CP',                                                'en' => 'CP settings'],
        'tour.party-settings.step.0.title' => ['es' => 'Configuración general',                                  'en' => 'General configuration'],
        'tour.party-settings.step.0.desc' => [
            'es' => 'En esta pestaña configuras el aspecto del CP: logo, nombre, server donde jugáis y si se exige captura para validar los reports.',
            'en' => 'In this tab you configure the CP basics: logo, name, server, and whether a screenshot is required to validate reports.',
        ],
        'tour.party-settings.step.1.title' => ['es' => 'Link de invitación',                                     'en' => 'Invite link'],
        'tour.party-settings.step.1.desc' => [
            'es' => 'El botón "Copiar invite" te da un enlace único con un código. Quien lo abra puede registrarse y queda en pending hasta que tú lo apruebes.',
            'en' => 'The "Copy invite" button gives you a unique URL. Anyone who opens it can sign up and lands in pending until you approve them.',
        ],

        // warehouse-personal tour
        'tour.warehouse-personal.title' => ['es' => 'Tu warehouse personal',                                     'en' => 'Your personal warehouse'],
        'tour.warehouse-personal.step.0.title' => ['es' => 'Lo que el CP te debe',                               'en' => 'What the CP owes you'],
        'tour.warehouse-personal.step.0.desc' => [
            'es' => 'Esta pantalla suma los items asignados a tu cuenta y la adena pendiente de cobro. Cada fila cita el report origen, así puedes verificar de dónde sale tu saldo.',
            'en' => 'This screen tallies items assigned to your account and adena pending payout. Each row cites the originating report so you can verify the source of your balance.',
        ],

        // vault-sell tour
        'tour.vault-sell.title' => ['es' => 'Vender items del vault',                                            'en' => 'Selling items from the vault'],
        'tour.vault-sell.step.0.title' => ['es' => 'Modal de venta',                                             'en' => 'Sell modal'],
        'tour.vault-sell.step.0.desc' => [
            'es' => 'Cuando vendes un item que está repartido entre varios farms, el modal lo divide automáticamente en orden FIFO (farm más antiguo primero) y crea una venta por farm origen.',
            'en' => 'When you sell an item that lives across several farms, the modal splits it FIFO (oldest farm first) and creates one sale per source farm.',
        ],
        'tour.vault-sell.step.1.title' => ['es' => 'Cada farm con sus reglas',                                   'en' => 'Each farm with its own rules'],
        'tour.vault-sell.step.1.desc' => [
            'es' => 'Cada venta usa el cp_share_pct y los attendees del farm origen — nunca se mezclan reglas entre farms. Si necesitas vender desde un farm concreto, "Pick a specific farm" te devuelve al flujo manual.',
            'en' => 'Each sale uses the cp_share_pct and attendees of the source farm — rules never mix between farms. If you need to sell from a specific source, "Pick a specific farm" falls back to the manual flow.',
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
