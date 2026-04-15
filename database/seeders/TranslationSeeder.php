<?php

namespace Database\Seeders;

use App\Contexts\System\Domain\Models\Translation;
use Illuminate\Database\Seeder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $translations = [
            'es' => [
                ['key' => 'app.name', 'value' => 'AdenaLedger'],
                ['key' => 'app.tagline', 'value' => 'Loot · Adena · Warehouse'],
                ['key' => 'lang.es', 'value' => 'ES'],
                ['key' => 'lang.en', 'value' => 'EN'],

                ['key' => 'footer.copyright', 'value' => '© {year} {appName}'],
                ['key' => 'footer.free', 'value' => '100% gratuito.'],
                ['key' => 'footer.donations_label', 'value' => 'Donaciones para cerveza:'],
                ['key' => 'footer.support_label', 'value' => 'Soporte:'],

                ['key' => 'common.close', 'value' => 'Cerrar'],
                ['key' => 'common.send', 'value' => 'Enviar'],
                ['key' => 'common.copy', 'value' => 'Copiar'],
                ['key' => 'common.optional', 'value' => '(opcional)'],

                ['key' => 'swal.copied.title', 'value' => '¡Copiada!'],
                ['key' => 'swal.copied.wallet', 'value' => 'La dirección de la cartera se ha copiado al portapapeles.'],
                ['key' => 'swal.sent.title', 'value' => 'Enviado'],
                ['key' => 'swal.sent.support', 'value' => 'Tu mensaje se ha enviado a soporte.'],
                ['key' => 'swal.sent.cp_request', 'value' => 'Te contactaremos con el link de invitación.'],
                ['key' => 'swal.error.title', 'value' => 'Error'],
                ['key' => 'swal.error.form', 'value' => 'Revisa los campos e inténtalo de nuevo.'],

                ['key' => 'welcome.meta.title', 'value' => 'Inicio'],
                ['key' => 'welcome.hero.title', 'value' => '{appName}'],
                ['key' => 'welcome.hero.subtitle', 'value' => 'Transparencia total para CPs de Lineage II: registra loot, reparte Adena y controla el warehouse con auditoría real.'],
                ['key' => 'welcome.hero.cta.login', 'value' => 'Entrar'],
                ['key' => 'welcome.hero.cta.register', 'value' => 'Tengo invitación'],
                ['key' => 'welcome.hero.cta.dashboard', 'value' => 'Ir al dashboard'],
                ['key' => 'welcome.hero.chips.audit', 'value' => 'Auditoría'],
                ['key' => 'welcome.hero.chips.adena', 'value' => 'Distribución de Adena'],
                ['key' => 'welcome.hero.chips.vault', 'value' => 'CP Vault'],
                ['key' => 'welcome.hero.chips.items', 'value' => 'Items DB'],

                ['key' => 'welcome.section.cp_cta.kicker', 'value' => '¿Tienes una CP?'],
                ['key' => 'welcome.section.cp_cta.title', 'value' => 'Regístrala y empieza gratis'],
                ['key' => 'welcome.section.cp_cta.text', 'value' => 'Envía una solicitud y te devolvemos un link de invitación para que tu CP se registre y empiece a usar el ledger.'],
                ['key' => 'welcome.section.cp_cta.btn', 'value' => 'Registrar mi CP'],
                ['key' => 'welcome.section.cp_cta.btn_alt', 'value' => 'Tengo dudas'],

                ['key' => 'welcome.section.about.kicker', 'value' => '¿Qué es {appName}?'],
                ['key' => 'welcome.section.about.title', 'value' => 'Un ledger para CPs de Lineage II'],
                ['key' => 'welcome.section.about.text', 'value' => 'Diseñado para registrar loot, distribuir Adena, controlar warehouse y mantener trazabilidad de todo lo que pasa en la party.'],
                ['key' => 'welcome.section.about.card.free.kicker', 'value' => '100%'],
                ['key' => 'welcome.section.about.card.free.value', 'value' => 'Gratis'],
                ['key' => 'welcome.section.about.card.support.kicker', 'value' => 'Soporte'],
                ['key' => 'welcome.section.about.card.donations.kicker', 'value' => 'Donaciones (cerveza)'],

                ['key' => 'welcome.section.chronicles.kicker', 'value' => 'Crónicas soportadas'],
                ['key' => 'welcome.section.chronicles.title', 'value' => 'C1 · C2 · C3 · C4 · C5 · IL · HB · Classic · LU4'],
                ['key' => 'welcome.section.chronicles.text', 'value' => 'Puedes crear CP por crónica y mantener el ledger separado por entorno.'],

                ['key' => 'welcome.section.how_it_works.kicker', 'value' => 'Cómo funciona'],
                ['key' => 'welcome.section.how_it_works.title', 'value' => 'De “loot” a ledger en minutos'],
                ['key' => 'welcome.section.how_it_works.text', 'value' => 'Registra la sesión, adjunta pruebas si hace falta y deja que el sistema calcule Adena y trazabilidad.'],
                ['key' => 'welcome.section.how_it_works.btn_donate', 'value' => 'Donar (cerveza)'],
                ['key' => 'welcome.section.how_it_works.btn_support', 'value' => 'Contactar soporte'],
                ['key' => 'welcome.section.how_it_works.steps.1.title', 'value' => 'Crea tu CP por crónica'],
                ['key' => 'welcome.section.how_it_works.steps.1.text', 'value' => 'Separa el ledger por entorno: C1–C5, IL, HB, Classic o LU4.'],
                ['key' => 'welcome.section.how_it_works.steps.2.title', 'value' => 'Registra loot y movimientos'],
                ['key' => 'welcome.section.how_it_works.steps.2.text', 'value' => 'Loot, Adena y warehouse quedan auditados por usuario y fecha.'],
                ['key' => 'welcome.section.how_it_works.steps.3.title', 'value' => 'Reparte y revisa con transparencia'],
                ['key' => 'welcome.section.how_it_works.steps.3.text', 'value' => 'Evita discusiones: números claros y trazabilidad completa.'],

                ['key' => 'welcome.features.loot.kicker', 'value' => 'Loot'],
                ['key' => 'welcome.features.loot.title', 'value' => 'Registro y pruebas'],
                ['key' => 'welcome.features.loot.text', 'value' => 'Reportes de sesión, adjuntos y trazabilidad por usuario.'],
                ['key' => 'welcome.features.adena.kicker', 'value' => 'Adena'],
                ['key' => 'welcome.features.adena.title', 'value' => 'Distribución'],
                ['key' => 'welcome.features.adena.text', 'value' => 'Reparto por miembro o al fondo CP con números claros.'],
                ['key' => 'welcome.features.warehouse.kicker', 'value' => 'Warehouse'],
                ['key' => 'welcome.features.warehouse.title', 'value' => 'Entradas y salidas'],
                ['key' => 'welcome.features.warehouse.text', 'value' => 'Control de stock y movimientos con responsable.'],
                ['key' => 'welcome.features.crafting.kicker', 'value' => 'Crafting'],
                ['key' => 'welcome.features.crafting.title', 'value' => 'Recetas y materiales'],
                ['key' => 'welcome.features.crafting.text', 'value' => 'Crafteo conectado al warehouse y a la DB de items.'],
                ['key' => 'welcome.features.roles.kicker', 'value' => 'Roles'],
                ['key' => 'welcome.features.roles.title', 'value' => 'CP Leader / Member'],
                ['key' => 'welcome.features.roles.text', 'value' => 'Vistas y permisos distintos según rol y estado.'],
                ['key' => 'welcome.features.audit.kicker', 'value' => 'Auditoría'],
                ['key' => 'welcome.features.audit.title', 'value' => 'Transparencia'],
                ['key' => 'welcome.features.audit.text', 'value' => 'Historial de acciones para detectar inconsistencias.'],

                ['key' => 'welcome.modal.support.title', 'value' => 'Soporte'],
                ['key' => 'welcome.modal.support.subject', 'value' => 'Asunto'],
                ['key' => 'welcome.modal.support.message', 'value' => 'Mensaje'],
                ['key' => 'welcome.modal.support.email', 'value' => 'Tu email {optional}'],
                ['key' => 'welcome.modal.support.name', 'value' => 'Tu nombre {optional}'],

                ['key' => 'welcome.modal.cp_request.title', 'value' => 'Registrar CP'],
                ['key' => 'welcome.modal.cp_request.cp_name', 'value' => 'Nombre de la CP'],
                ['key' => 'welcome.modal.cp_request.server', 'value' => 'Servidor {optional}'],
                ['key' => 'welcome.modal.cp_request.chronicle', 'value' => 'Crónica'],
                ['key' => 'welcome.modal.cp_request.leader', 'value' => 'Líder {optional}'],
                ['key' => 'welcome.modal.cp_request.email', 'value' => 'Email de contacto'],
                ['key' => 'welcome.modal.cp_request.message', 'value' => 'Mensaje {optional}'],

                ['key' => 'welcome.modal.donation.title', 'value' => 'Apoya el proyecto'],
                ['key' => 'welcome.modal.donation.text', 'value' => '{appName} es 100% gratuito. Si te ayuda y quieres apoyar el desarrollo, se aceptan donaciones para cerveza en la siguiente cartera:'],
                ['key' => 'welcome.modal.donation.btn_copy', 'value' => 'Copiar cartera'],

                ['key' => 'admin.toast.cp_created', 'value' => 'CP creada. Link de invitación disponible.'],
                ['key' => 'admin.toast.cp_create_error', 'value' => 'No se pudo crear la CP. Revisa los campos.'],
                ['key' => 'admin.activity.title', 'value' => 'Actividad Global'],
                ['key' => 'admin.activity.subtitle', 'value' => 'Últimos 7 días'],
                ['key' => 'admin.cp_requests.title', 'value' => 'Solicitudes de CP'],
                ['key' => 'admin.cp_requests.subtitle', 'value' => 'Pendientes de aprobación'],
                ['key' => 'admin.cp_requests.none', 'value' => 'Sin solicitudes pendientes.'],
                ['key' => 'admin.cps.title', 'value' => 'CPs registradas'],
                ['key' => 'admin.create_modal.title', 'value' => 'Crear CP'],
                ['key' => 'admin.invite_link.title', 'value' => 'Link de invitación'],
                ['key' => 'admin.items.title', 'value' => 'Base de ítems'],
                ['key' => 'admin.items.total', 'value' => 'Total de ítems'],
                ['key' => 'admin.dashboard.total_cps', 'value' => 'Total de CPs'],
                ['key' => 'admin.dashboard.total_items', 'value' => 'Total de ítems registrados'],
                ['key' => 'admin.kpis.total_cps', 'value' => 'Total de CPs'],
                ['key' => 'admin.kpis.total_cps_hint', 'value' => 'CPs registradas en el sistema'],
                ['key' => 'admin.kpis.total_members', 'value' => 'Total de miembros'],
                ['key' => 'admin.kpis.total_members_hint', 'value' => 'Miembros activos registrados'],
                ['key' => 'admin.kpis.total_points', 'value' => 'Puntos globales'],
                ['key' => 'admin.kpis.total_points_hint', 'value' => 'Puntos acumulados'],
                ['key' => 'admin.kpis.total_reports', 'value' => 'Total de reportes'],
                ['key' => 'admin.kpis.total_reports_hint', 'value' => 'Sesiones de loot reportadas'],
                ['key' => 'alerts.title', 'value' => 'Alertas'],
                ['key' => 'cp.activity.title', 'value' => 'Actividad de la CP'],
                ['key' => 'cp.adena_pending.title', 'value' => 'Adena pendiente'],
                ['key' => 'cp.adena_pending.subtitle', 'value' => 'Miembros con deuda'],
                ['key' => 'cp.latest_items.title', 'value' => 'Últimos ítems'],
                ['key' => 'cp.metrics.title', 'value' => 'Métricas de la CP'],
                ['key' => 'cp.tasks.title', 'value' => 'Tareas'],
                ['key' => 'cp.tasks.subtitle', 'value' => 'Estado semanal'],
                ['key' => 'cp.tasks.bosses', 'value' => 'Jefes'],
                ['key' => 'loot.modal.title', 'value' => 'Reportar loot'],
                ['key' => 'loot.modal.item_search_placeholder', 'value' => 'Buscar ítem...'],
                ['key' => 'loot.title', 'value' => 'Loot'],
                ['key' => 'loot.report_session', 'value' => 'Reportar sesión'],
                ['key' => 'member.actions.report_session', 'value' => 'Reportar sesión'],
                ['key' => 'member.cp_activity.title', 'value' => 'Actividad de la CP'],
                ['key' => 'member.cp_latest_drops.title', 'value' => 'Últimos drops de la CP'],
                ['key' => 'member.latest_items.title', 'value' => 'Mis últimos ítems'],
                ['key' => 'member.latest_items.subtitle', 'value' => 'Asignaciones recientes'],
                ['key' => 'member.summary.title', 'value' => 'Resumen personal'],
                ['key' => 'member.title', 'value' => 'Mi Panel'],
                ['key' => 'member.week.title', 'value' => 'Ranking semanal'],
                ['key' => 'member.week.subtitle', 'value' => 'Top de la CP'],
                ['key' => 'modal.cp_request.title', 'value' => 'Registrar CP'],
                ['key' => 'modal.donations.title', 'value' => 'Donaciones'],
                ['key' => 'modal.support.title', 'value' => 'Soporte'],
                ['key' => 'profile.info.title', 'value' => 'Información del perfil'],
                ['key' => 'profile.password.title', 'value' => 'Actualizar contraseña'],
                ['key' => 'craft.title', 'value' => 'Crafteo'],

                ['key' => 'common.actions', 'value' => 'Acciones'],
                ['key' => 'common.add', 'value' => 'Añadir'],
                ['key' => 'common.adena', 'value' => 'Adena'],
                ['key' => 'common.allowed_images', 'value' => 'PNG, JPG o WEBP'],
                ['key' => 'common.allowed_images_max_4mb', 'value' => 'PNG, JPG o WEBP (Máx. 4MB)'],
                ['key' => 'common.amount', 'value' => 'Cantidad'],
                ['key' => 'common.approve', 'value' => 'Aprobar'],
                ['key' => 'common.assign', 'value' => 'Asignar'],
                ['key' => 'common.cancel', 'value' => 'Cancelar'],
                ['key' => 'common.click_to_upload', 'value' => 'Hacer clic para subir'],
                ['key' => 'common.delete', 'value' => 'Eliminar'],
                ['key' => 'common.description', 'value' => 'Descripción'],
                ['key' => 'common.done', 'value' => 'Hecho'],
                ['key' => 'common.down', 'value' => 'Bajar'],
                ['key' => 'common.edit', 'value' => 'Editar'],
                ['key' => 'common.error', 'value' => 'Error'],
                ['key' => 'common.excluded', 'value' => 'Excluido'],
                ['key' => 'common.image', 'value' => 'Imagen'],
                ['key' => 'common.image_captured', 'value' => 'Imagen Capturada'],
                ['key' => 'common.item', 'value' => 'Ítem'],
                ['key' => 'common.items', 'value' => 'Ítems'],
                ['key' => 'common.loading', 'value' => 'Cargando...'],
                ['key' => 'common.member', 'value' => 'Miembro'],
                ['key' => 'common.missing', 'value' => 'Faltante'],
                ['key' => 'common.na', 'value' => 'N/A'],
                ['key' => 'common.name', 'value' => 'Nombre'],
                ['key' => 'common.no', 'value' => 'No'],
                ['key' => 'common.no_cp', 'value' => 'Sin CP'],
                ['key' => 'common.no_description', 'value' => 'Sin descripción.'],
                ['key' => 'common.no_results', 'value' => 'Sin resultados.'],
                ['key' => 'common.pending', 'value' => 'Pendiente'],
                ['key' => 'common.progress', 'value' => 'Progreso'],
                ['key' => 'common.read_only', 'value' => 'Sólo Lectura'],
                ['key' => 'common.remove', 'value' => 'Quitar'],
                ['key' => 'common.retry', 'value' => 'Reintentar'],
                ['key' => 'common.save', 'value' => 'Guardar'],
                ['key' => 'common.save_changes', 'value' => 'Guardar Cambios'],
                ['key' => 'common.search_item_placeholder', 'value' => 'Buscar item...'],
                ['key' => 'common.searching', 'value' => 'Buscando...'],
                ['key' => 'common.select_member', 'value' => 'Selecciona un miembro'],
                ['key' => 'common.sell', 'value' => 'Vender'],
                ['key' => 'common.unknown', 'value' => 'Desconocido'],
                ['key' => 'common.up', 'value' => 'Subir'],
                ['key' => 'common.view_history', 'value' => 'Ver histórico'],
                ['key' => 'common.yes', 'value' => 'Sí'],

                ['key' => 'nav.warehouse', 'value' => 'Warehouse'],

                ['key' => 'warehouse.join_cp_title', 'value' => 'Únete a una CP'],
                ['key' => 'warehouse.join_cp_text', 'value' => 'Necesitas estar asignado a una CP para ver tu warehouse.'],
                ['key' => 'warehouse.assigned_items_kicker', 'value' => 'Items asignados'],
                ['key' => 'warehouse.adena_owed', 'value' => 'Adena a deber'],
                ['key' => 'warehouse.adena_paid', 'value' => 'Adena pagada'],
                ['key' => 'warehouse.filter_item_placeholder', 'value' => 'Filtrar item...'],
                ['key' => 'warehouse.no_items_assigned', 'value' => 'No tienes items asignados.'],
                ['key' => 'warehouse.return', 'value' => 'Devolver'],
                ['key' => 'warehouse.return_item', 'value' => 'Devolver Ítem'],
                ['key' => 'warehouse.assigned', 'value' => 'Asignado'],
                ['key' => 'warehouse.trade_screenshot_required', 'value' => 'Captura del Trade (obligatoria)'],
                ['key' => 'warehouse.toast.return_requested', 'value' => 'Solicitud de devolución enviada.'],
                ['key' => 'warehouse.toast.return_failed', 'value' => 'No se pudo enviar la solicitud de devolución.'],

                ['key' => 'system.users.page_title', 'value' => 'Gestión de Miembros'],
                ['key' => 'system.users.header_title', 'value' => 'Gestión de Miembros'],
                ['key' => 'system.users.subtitle_admin', 'value' => 'Auditoría Global de Usuarios y Balances'],
                ['key' => 'system.users.subtitle_leader', 'value' => 'Gestión de Saldo y Auditoría de CP'],
                ['key' => 'system.users.search_placeholder', 'value' => 'Buscar por nombre o email...'],
                ['key' => 'system.users.total_users', 'value' => 'Usuarios Totales'],
                ['key' => 'system.users.total_adena', 'value' => 'Adena Acumulada (Total)'],
                ['key' => 'system.users.total_points', 'value' => 'Puntos Totales'],
                ['key' => 'system.users.member', 'value' => 'Miembro'],
                ['key' => 'system.users.role_cp', 'value' => 'Rol / CP'],
                ['key' => 'system.users.adena_balance', 'value' => 'Saldo Adena'],
                ['key' => 'system.users.points_balance', 'value' => 'Saldo Puntos'],
                ['key' => 'system.users.actions.manage_adena', 'value' => 'Gestionar Saldo Adena'],
                ['key' => 'system.users.actions.edit_role_cp', 'value' => 'Editar Rol/CP'],
                ['key' => 'system.users.actions.delete_user', 'value' => 'Eliminar Usuario'],
                ['key' => 'system.users.actions.reactivate_user', 'value' => 'Reactivar Usuario'],
                ['key' => 'system.users.actions.ban_user', 'value' => 'Excluir/Banear Usuario'],
                ['key' => 'system.users.adena_movements', 'value' => 'Pagos / Movimientos de Adena'],
                ['key' => 'system.users.no_adena_movements', 'value' => 'Sin movimientos de Adena.'],
                ['key' => 'system.users.action_audit', 'value' => 'Auditoría de Acciones'],
                ['key' => 'system.users.no_actions', 'value' => 'Sin acciones registradas.'],
                ['key' => 'system.users.no_users_found', 'value' => 'No se encontraron usuarios...'],
                ['key' => 'system.users.balance_management', 'value' => 'Gestión de Saldo'],
                ['key' => 'system.users.adena_amount', 'value' => 'Monto de Adena'],
                ['key' => 'system.users.adena_amount_placeholder', 'value' => 'Ej: -1000000 para pagar, 500000 para abonar'],
                ['key' => 'system.users.negative_values_tip', 'value' => 'Usa valores negativos para descontar del fondo (pagos)'],
                ['key' => 'system.users.reason_description', 'value' => 'Motivo / Descripción'],
                ['key' => 'system.users.reason_placeholder', 'value' => 'Ej: Pago de drop Draconic Bow'],
                ['key' => 'system.users.trade_screenshot_required', 'value' => 'Captura del Trade (obligatoria)'],
                ['key' => 'system.users.register_transaction', 'value' => 'Registrar Transacción'],
                ['key' => 'system.users.reassign_user', 'value' => 'Reasignar Usuario'],
                ['key' => 'system.users.system_role', 'value' => 'Rol del Sistema'],
                ['key' => 'system.users.assign_cp', 'value' => 'Asignar a CP'],
                ['key' => 'system.users.no_cp_option', 'value' => 'Ninguna / Solo'],
                ['key' => 'system.users.update_assignment', 'value' => 'Actualizar Asignación'],
                ['key' => 'system.users.swal.delete_title', 'value' => 'Eliminar Usuario'],
                ['key' => 'system.users.swal.delete_text', 'value' => '¿Eliminar a {name}? Esta acción no se puede deshacer.'],
                ['key' => 'system.users.swal.ban_title', 'value' => 'Excluir Usuario'],
                ['key' => 'system.users.swal.ban_text', 'value' => '¿Excluir a {name}? Podrá ser reactivado más tarde.'],
                ['key' => 'system.users.swal.ban_confirm', 'value' => 'Excluir'],
                ['key' => 'system.users.swal.unban_title', 'value' => 'Reactivar Usuario'],
                ['key' => 'system.users.swal.unban_text', 'value' => '¿Reactivar a {name}?'],
                ['key' => 'system.users.swal.unban_confirm', 'value' => 'Reactivar'],
                ['key' => 'system.users.audit.role_change', 'value' => 'Rol: {from} → {to}'],
                ['key' => 'system.users.audit.cp_change', 'value' => 'CP: {from} → {to}'],
                ['key' => 'system.users.audit.updated', 'value' => 'Actualizado'],
                ['key' => 'system.users.audit.user_deleted', 'value' => 'Usuario eliminado'],
                ['key' => 'system.users.audit.adena_adjusted', 'value' => 'Adena ajustada ({amount})'],
                ['key' => 'system.users.audit.adena_adjusted_with_desc', 'value' => 'Adena ajustada ({amount}) · {description}'],

                ['key' => 'system.items.items_found', 'value' => 'items encontrados'],
                ['key' => 'system.items.search_item', 'value' => 'Buscar Ítem'],
                ['key' => 'system.items.search_placeholder', 'value' => 'Ej: Angel Slayer...'],
                ['key' => 'system.items.chronicle', 'value' => 'Crónica'],
                ['key' => 'system.items.all_chronicles', 'value' => 'Todas las Crónicas'],
                ['key' => 'system.items.grade', 'value' => 'Rango / Grado'],
                ['key' => 'system.items.all_grades', 'value' => 'Todos los Grados'],
                ['key' => 'system.items.category', 'value' => 'Categoría'],
                ['key' => 'system.items.all_categories', 'value' => 'Todas las Categorías'],
                ['key' => 'system.items.base_points', 'value' => 'Puntos Base'],
                ['key' => 'system.items.edit_item', 'value' => 'Editar Ítem'],
                ['key' => 'system.items.details', 'value' => 'Detalle'],
                ['key' => 'system.items.external_id', 'value' => 'ID Externo'],
                ['key' => 'system.items.icon_name', 'value' => 'Nombre de Icono'],

                ['key' => 'party.head_title', 'value' => 'Party'],
                ['key' => 'party.join.title', 'value' => 'Únete a una CP'],
                ['key' => 'party.join.subtitle', 'value' => 'Necesitas estar asignado a una CP para acceder a la party.'],
                ['key' => 'party.invite.label', 'value' => 'Invitación'],
                ['key' => 'party.cp_leader', 'value' => 'CP Leader'],
                ['key' => 'party.tabs.members', 'value' => 'Miembros'],
                ['key' => 'party.tabs.vault', 'value' => 'Warehouse'],
                ['key' => 'party.tabs.crafting', 'value' => 'Crafting'],
                ['key' => 'party.tabs.points_settings', 'value' => 'Puntos'],
                ['key' => 'party.member.badge_leader', 'value' => 'Leader'],
                ['key' => 'party.member.owed', 'value' => 'A deber'],
                ['key' => 'party.member.paid', 'value' => 'Pagado'],
                ['key' => 'party.member.warehouse_title', 'value' => 'Warehouse de {name}'],
                ['key' => 'party.member.warehouse_load_failed', 'value' => 'No se pudo cargar el warehouse.'],
                ['key' => 'party.member.warehouse_empty', 'value' => 'Sin items.'],
                ['key' => 'party.member.audit_title', 'value' => 'Auditoría de {name}'],
                ['key' => 'party.member.audit_load_failed', 'value' => 'No se pudo cargar la auditoría.'],
                ['key' => 'party.member.adena_payments_title', 'value' => 'Pagos / Movimientos de Adena'],
                ['key' => 'party.member.adena_payments_empty', 'value' => 'Sin movimientos de Adena.'],
                ['key' => 'party.member.view_history', 'value' => 'Ver histórico'],
                ['key' => 'party.member.actions_audit_title', 'value' => 'Auditoría de Acciones'],
                ['key' => 'party.member.actions_audit_empty', 'value' => 'Sin acciones registradas.'],
                ['key' => 'party.vault.title', 'value' => 'Warehouse CP'],
                ['key' => 'party.vault.subtitle', 'value' => 'Stock y adena de la CP.'],
                ['key' => 'party.vault.add_items', 'value' => 'Añadir items'],
                ['key' => 'party.vault.adena_in_warehouse', 'value' => 'Adena en warehouse'],
                ['key' => 'party.vault.adena_owed', 'value' => 'Adena a deber'],
                ['key' => 'party.vault.adena_paid', 'value' => 'Adena pagada'],
                ['key' => 'party.vault.filter_placeholder', 'value' => 'Filtrar item...'],
                ['key' => 'party.vault.empty_filtered', 'value' => 'Sin resultados.'],
                ['key' => 'party.vault.empty', 'value' => 'Sin items.'],
                ['key' => 'party.assign_from_warehouse', 'value' => 'Asignar Ítem desde Warehouse'],
                ['key' => 'party.in_vault', 'value' => 'En baúl:'],
                ['key' => 'party.trade_screenshot_required', 'value' => 'Captura del Trade (obligatoria)'],
                ['key' => 'party.assign', 'value' => 'Asignar'],
                ['key' => 'party.sell_from_warehouse', 'value' => 'Vender Ítem desde Warehouse'],
                ['key' => 'party.total_sale', 'value' => 'Total Venta'],
                ['key' => 'party.unit_price', 'value' => 'Precio / Unidad'],
                ['key' => 'party.adena_destination', 'value' => 'Destino Adena'],
                ['key' => 'party.cp_fund', 'value' => 'Fondo CP'],
                ['key' => 'party.no_split_desc', 'value' => 'No genera split a miembros.'],
                ['key' => 'party.split', 'value' => 'Split'],
                ['key' => 'party.split_desc', 'value' => 'Genera Adena a deber por miembro.'],
                ['key' => 'party.split_members', 'value' => 'Miembros para split'],
                ['key' => 'party.sale_screenshot_required', 'value' => 'Captura de la Venta (obligatoria)'],
                ['key' => 'party.register_sale', 'value' => 'Registrar Venta'],
                ['key' => 'party.add_items_to_warehouse', 'value' => 'Añadir Items al Warehouse'],
                ['key' => 'party.add_adena', 'value' => 'Añadir Adena'],
                ['key' => 'party.lines', 'value' => 'Líneas'],
                ['key' => 'party.units', 'value' => 'Unidades'],
                ['key' => 'party.screenshot_required', 'value' => 'Captura (obligatoria)'],

                ['key' => 'craft.title', 'value' => 'Crafting'],
                ['key' => 'craft.subtitle', 'value' => 'Recetas, materiales y progreso.'],
                ['key' => 'craft.search_placeholder', 'value' => 'Buscar receta...'],
                ['key' => 'craft.search_failed', 'value' => 'No se pudo buscar.'],
                ['key' => 'craft.success', 'value' => 'éxito'],
                ['key' => 'craft.no_recipes', 'value' => 'No hay recetas.'],
                ['key' => 'craft.priority', 'value' => 'Prioridad: {value}'],
                ['key' => 'craft.recipe_fallback', 'value' => 'Receta'],
                ['key' => 'craft.outputs', 'value' => 'Resultados'],
                ['key' => 'craft.output', 'value' => 'Resultado'],
                ['key' => 'craft.actions.craft', 'value' => 'Craftear'],
                ['key' => 'craft.materials', 'value' => 'Materiales'],
                ['key' => 'craft.no_materials', 'value' => 'Sin materiales.'],
                ['key' => 'craft.material_fallback', 'value' => 'Material'],
                ['key' => 'craft.craftable', 'value' => 'Crafteable'],
                ['key' => 'craft.tree.hide', 'value' => 'Ocultar árbol'],
                ['key' => 'craft.tree.show', 'value' => 'Ver árbol'],
                ['key' => 'craft.tree.title', 'value' => 'Árbol de crafteo'],
                ['key' => 'craft.tree.base_materials', 'value' => 'Materiales base'],
                ['key' => 'craft.tree.empty', 'value' => 'Sin datos.'],
                ['key' => 'craft.confirm.title', 'value' => 'Confirmar crafteo'],
                ['key' => 'craft.confirm.subtitle', 'value' => '¿Marcar esta receta como crafteada?'],

                ['key' => 'party.points.title', 'value' => 'Puntos'],
                ['key' => 'party.points.subtitle', 'value' => 'Configuración de puntos base.'],
                ['key' => 'party.points.current', 'value' => 'Actual'],
                ['key' => 'party.points.pts', 'value' => 'pts'],
                ['key' => 'party.points.hint', 'value' => 'Define puntos base por item.'],
            ],
            'en' => [
                ['key' => 'app.name', 'value' => 'AdenaLedger'],
                ['key' => 'app.tagline', 'value' => 'Loot · Adena · Warehouse'],
                ['key' => 'lang.es', 'value' => 'ES'],
                ['key' => 'lang.en', 'value' => 'EN'],

                ['key' => 'footer.copyright', 'value' => '© {year} {appName}'],
                ['key' => 'footer.free', 'value' => '100% free.'],
                ['key' => 'footer.donations_label', 'value' => 'Beer donations:'],
                ['key' => 'footer.support_label', 'value' => 'Support:'],

                ['key' => 'common.close', 'value' => 'Close'],
                ['key' => 'common.send', 'value' => 'Send'],
                ['key' => 'common.copy', 'value' => 'Copy'],
                ['key' => 'common.optional', 'value' => '(optional)'],

                ['key' => 'swal.copied.title', 'value' => 'Copied!'],
                ['key' => 'swal.copied.wallet', 'value' => 'Wallet address copied to clipboard.'],
                ['key' => 'swal.sent.title', 'value' => 'Sent'],
                ['key' => 'swal.sent.support', 'value' => 'Your message has been sent to support.'],
                ['key' => 'swal.sent.cp_request', 'value' => 'We will contact you with an invitation link.'],
                ['key' => 'swal.error.title', 'value' => 'Error'],
                ['key' => 'swal.error.form', 'value' => 'Check the fields and try again.'],

                ['key' => 'welcome.meta.title', 'value' => 'Home'],
                ['key' => 'welcome.hero.title', 'value' => '{appName}'],
                ['key' => 'welcome.hero.subtitle', 'value' => 'Total transparency for Lineage II CPs: track loot, split Adena, and manage the warehouse with real audit trails.'],
                ['key' => 'welcome.hero.cta.login', 'value' => 'Sign in'],
                ['key' => 'welcome.hero.cta.register', 'value' => 'I have an invite'],
                ['key' => 'welcome.hero.cta.dashboard', 'value' => 'Go to dashboard'],
                ['key' => 'welcome.hero.chips.audit', 'value' => 'Audit'],
                ['key' => 'welcome.hero.chips.adena', 'value' => 'Adena splitting'],
                ['key' => 'welcome.hero.chips.vault', 'value' => 'CP Vault'],
                ['key' => 'welcome.hero.chips.items', 'value' => 'Items DB'],

                ['key' => 'welcome.section.cp_cta.kicker', 'value' => 'Have a CP?'],
                ['key' => 'welcome.section.cp_cta.title', 'value' => 'Register it and start for free'],
                ['key' => 'welcome.section.cp_cta.text', 'value' => 'Send a request and we will provide an invitation link so your CP can register and start using the ledger.'],
                ['key' => 'welcome.section.cp_cta.btn', 'value' => 'Register my CP'],
                ['key' => 'welcome.section.cp_cta.btn_alt', 'value' => 'I have questions'],

                ['key' => 'welcome.section.about.kicker', 'value' => 'What is {appName}?'],
                ['key' => 'welcome.section.about.title', 'value' => 'A ledger for Lineage II CPs'],
                ['key' => 'welcome.section.about.text', 'value' => 'Built to track loot, split Adena, manage the warehouse and keep full traceability of everything that happens in the party.'],
                ['key' => 'welcome.section.about.card.free.kicker', 'value' => '100%'],
                ['key' => 'welcome.section.about.card.free.value', 'value' => 'Free'],
                ['key' => 'welcome.section.about.card.support.kicker', 'value' => 'Support'],
                ['key' => 'welcome.section.about.card.donations.kicker', 'value' => 'Donations (beer)'],

                ['key' => 'welcome.section.chronicles.kicker', 'value' => 'Supported chronicles'],
                ['key' => 'welcome.section.chronicles.title', 'value' => 'C1 · C2 · C3 · C4 · C5 · IL · HB · Classic · LU4'],
                ['key' => 'welcome.section.chronicles.text', 'value' => 'Create one CP per chronicle and keep ledgers separated by environment.'],

                ['key' => 'welcome.section.how_it_works.kicker', 'value' => 'How it works'],
                ['key' => 'welcome.section.how_it_works.title', 'value' => 'From “loot” to ledger in minutes'],
                ['key' => 'welcome.section.how_it_works.text', 'value' => 'Record the session, attach proof if needed, and let the system handle Adena splitting and traceability.'],
                ['key' => 'welcome.section.how_it_works.btn_donate', 'value' => 'Donate (beer)'],
                ['key' => 'welcome.section.how_it_works.btn_support', 'value' => 'Contact support'],
                ['key' => 'welcome.section.how_it_works.steps.1.title', 'value' => 'Create your CP per chronicle'],
                ['key' => 'welcome.section.how_it_works.steps.1.text', 'value' => 'Keep ledgers separated by environment: C1–C5, IL, HB, Classic or LU4.'],
                ['key' => 'welcome.section.how_it_works.steps.2.title', 'value' => 'Track loot and movements'],
                ['key' => 'welcome.section.how_it_works.steps.2.text', 'value' => 'Loot, Adena and warehouse are audited by user and date.'],
                ['key' => 'welcome.section.how_it_works.steps.3.title', 'value' => 'Split and review transparently'],
                ['key' => 'welcome.section.how_it_works.steps.3.text', 'value' => 'Avoid drama: clear numbers and full traceability.'],

                ['key' => 'welcome.features.loot.kicker', 'value' => 'Loot'],
                ['key' => 'welcome.features.loot.title', 'value' => 'Logs and proof'],
                ['key' => 'welcome.features.loot.text', 'value' => 'Session reports, attachments and user-level traceability.'],
                ['key' => 'welcome.features.adena.kicker', 'value' => 'Adena'],
                ['key' => 'welcome.features.adena.title', 'value' => 'Splitting'],
                ['key' => 'welcome.features.adena.text', 'value' => 'Split per member or to the CP fund with clear numbers.'],
                ['key' => 'welcome.features.warehouse.kicker', 'value' => 'Warehouse'],
                ['key' => 'welcome.features.warehouse.title', 'value' => 'In & out'],
                ['key' => 'welcome.features.warehouse.text', 'value' => 'Stock control and movements with responsible user.'],
                ['key' => 'welcome.features.crafting.kicker', 'value' => 'Crafting'],
                ['key' => 'welcome.features.crafting.title', 'value' => 'Recipes & mats'],
                ['key' => 'welcome.features.crafting.text', 'value' => 'Crafting connected to warehouse and the items DB.'],
                ['key' => 'welcome.features.roles.kicker', 'value' => 'Roles'],
                ['key' => 'welcome.features.roles.title', 'value' => 'CP Leader / Member'],
                ['key' => 'welcome.features.roles.text', 'value' => 'Different views and permissions based on role and status.'],
                ['key' => 'welcome.features.audit.kicker', 'value' => 'Audit'],
                ['key' => 'welcome.features.audit.title', 'value' => 'Transparency'],
                ['key' => 'welcome.features.audit.text', 'value' => 'Action history to spot inconsistencies.'],

                ['key' => 'welcome.modal.support.title', 'value' => 'Support'],
                ['key' => 'welcome.modal.support.subject', 'value' => 'Subject'],
                ['key' => 'welcome.modal.support.message', 'value' => 'Message'],
                ['key' => 'welcome.modal.support.email', 'value' => 'Your email {optional}'],
                ['key' => 'welcome.modal.support.name', 'value' => 'Your name {optional}'],

                ['key' => 'welcome.modal.cp_request.title', 'value' => 'Register CP'],
                ['key' => 'welcome.modal.cp_request.cp_name', 'value' => 'CP name'],
                ['key' => 'welcome.modal.cp_request.server', 'value' => 'Server {optional}'],
                ['key' => 'welcome.modal.cp_request.chronicle', 'value' => 'Chronicle'],
                ['key' => 'welcome.modal.cp_request.leader', 'value' => 'Leader {optional}'],
                ['key' => 'welcome.modal.cp_request.email', 'value' => 'Contact email'],
                ['key' => 'welcome.modal.cp_request.message', 'value' => 'Message {optional}'],

                ['key' => 'welcome.modal.donation.title', 'value' => 'Support the project'],
                ['key' => 'welcome.modal.donation.text', 'value' => '{appName} is 100% free. If it helps you and you want to support development, beer donations are welcome at this wallet address:'],
                ['key' => 'welcome.modal.donation.btn_copy', 'value' => 'Copy wallet'],

                ['key' => 'admin.toast.cp_created', 'value' => 'CP created. Invitation link available.'],
                ['key' => 'admin.toast.cp_create_error', 'value' => 'Could not create CP. Check the fields.'],
                ['key' => 'admin.activity.title', 'value' => 'Global Activity'],
                ['key' => 'admin.activity.subtitle', 'value' => 'Last 7 days'],
                ['key' => 'admin.cp_requests.title', 'value' => 'CP Requests'],
                ['key' => 'admin.cp_requests.subtitle', 'value' => 'Pending approval'],
                ['key' => 'admin.cp_requests.none', 'value' => 'No pending requests.'],
                ['key' => 'admin.cps.title', 'value' => 'Registered CPs'],
                ['key' => 'admin.create_modal.title', 'value' => 'Create CP'],
                ['key' => 'admin.invite_link.title', 'value' => 'Invite Link'],
                ['key' => 'admin.items.title', 'value' => 'Item Database'],
                ['key' => 'admin.items.total', 'value' => 'Total items'],
                ['key' => 'admin.dashboard.total_cps', 'value' => 'Total CPs'],
                ['key' => 'admin.dashboard.total_items', 'value' => 'Total registered items'],
                ['key' => 'admin.kpis.total_cps', 'value' => 'Total CPs'],
                ['key' => 'admin.kpis.total_cps_hint', 'value' => 'CPs registered in the system'],
                ['key' => 'admin.kpis.total_members', 'value' => 'Total members'],
                ['key' => 'admin.kpis.total_members_hint', 'value' => 'Active registered members'],
                ['key' => 'admin.kpis.total_points', 'value' => 'Global points'],
                ['key' => 'admin.kpis.total_points_hint', 'value' => 'Accumulated points'],
                ['key' => 'admin.kpis.total_reports', 'value' => 'Total reports'],
                ['key' => 'admin.kpis.total_reports_hint', 'value' => 'Reported loot sessions'],
                ['key' => 'alerts.title', 'value' => 'Alerts'],
                ['key' => 'cp.activity.title', 'value' => 'CP Activity'],
                ['key' => 'cp.adena_pending.title', 'value' => 'Pending Adena'],
                ['key' => 'cp.adena_pending.subtitle', 'value' => 'Members with debt'],
                ['key' => 'cp.latest_items.title', 'value' => 'Latest items'],
                ['key' => 'cp.metrics.title', 'value' => 'CP Metrics'],
                ['key' => 'cp.tasks.title', 'value' => 'Tasks'],
                ['key' => 'cp.tasks.subtitle', 'value' => 'Weekly status'],
                ['key' => 'cp.tasks.bosses', 'value' => 'Bosses'],
                ['key' => 'loot.modal.title', 'value' => 'Report loot'],
                ['key' => 'loot.modal.item_search_placeholder', 'value' => 'Search item...'],
                ['key' => 'loot.title', 'value' => 'Loot'],
                ['key' => 'loot.report_session', 'value' => 'Report session'],
                ['key' => 'member.actions.report_session', 'value' => 'Report session'],
                ['key' => 'member.cp_activity.title', 'value' => 'CP Activity'],
                ['key' => 'member.cp_latest_drops.title', 'value' => 'Latest CP drops'],
                ['key' => 'member.latest_items.title', 'value' => 'My latest items'],
                ['key' => 'member.latest_items.subtitle', 'value' => 'Recent assignments'],
                ['key' => 'member.summary.title', 'value' => 'Personal summary'],
                ['key' => 'member.title', 'value' => 'My Dashboard'],
                ['key' => 'member.week.title', 'value' => 'Weekly ranking'],
                ['key' => 'member.week.subtitle', 'value' => 'CP top'],
                ['key' => 'modal.cp_request.title', 'value' => 'Register CP'],
                ['key' => 'modal.donations.title', 'value' => 'Donations'],
                ['key' => 'modal.support.title', 'value' => 'Support'],
                ['key' => 'profile.info.title', 'value' => 'Profile information'],
                ['key' => 'profile.password.title', 'value' => 'Update password'],
                ['key' => 'craft.title', 'value' => 'Crafting'],

                ['key' => 'common.actions', 'value' => 'Actions'],
                ['key' => 'common.add', 'value' => 'Add'],
                ['key' => 'common.adena', 'value' => 'Adena'],
                ['key' => 'common.allowed_images', 'value' => 'PNG, JPG or WEBP'],
                ['key' => 'common.allowed_images_max_4mb', 'value' => 'PNG, JPG or WEBP (Max 4MB)'],
                ['key' => 'common.amount', 'value' => 'Amount'],
                ['key' => 'common.approve', 'value' => 'Approve'],
                ['key' => 'common.assign', 'value' => 'Assign'],
                ['key' => 'common.cancel', 'value' => 'Cancel'],
                ['key' => 'common.click_to_upload', 'value' => 'Click to upload'],
                ['key' => 'common.delete', 'value' => 'Delete'],
                ['key' => 'common.description', 'value' => 'Description'],
                ['key' => 'common.done', 'value' => 'Done'],
                ['key' => 'common.down', 'value' => 'Down'],
                ['key' => 'common.edit', 'value' => 'Edit'],
                ['key' => 'common.error', 'value' => 'Error'],
                ['key' => 'common.excluded', 'value' => 'Excluded'],
                ['key' => 'common.image', 'value' => 'Image'],
                ['key' => 'common.image_captured', 'value' => 'Image Captured'],
                ['key' => 'common.item', 'value' => 'Item'],
                ['key' => 'common.items', 'value' => 'Items'],
                ['key' => 'common.loading', 'value' => 'Loading...'],
                ['key' => 'common.member', 'value' => 'Member'],
                ['key' => 'common.missing', 'value' => 'Missing'],
                ['key' => 'common.na', 'value' => 'N/A'],
                ['key' => 'common.name', 'value' => 'Name'],
                ['key' => 'common.no', 'value' => 'No'],
                ['key' => 'common.no_cp', 'value' => 'No CP'],
                ['key' => 'common.no_description', 'value' => 'No description.'],
                ['key' => 'common.no_results', 'value' => 'No results.'],
                ['key' => 'common.pending', 'value' => 'Pending'],
                ['key' => 'common.progress', 'value' => 'Progress'],
                ['key' => 'common.read_only', 'value' => 'Read Only'],
                ['key' => 'common.remove', 'value' => 'Remove'],
                ['key' => 'common.retry', 'value' => 'Retry'],
                ['key' => 'common.save', 'value' => 'Save'],
                ['key' => 'common.save_changes', 'value' => 'Save Changes'],
                ['key' => 'common.search_item_placeholder', 'value' => 'Search item...'],
                ['key' => 'common.searching', 'value' => 'Searching...'],
                ['key' => 'common.select_member', 'value' => 'Select a member'],
                ['key' => 'common.sell', 'value' => 'Sell'],
                ['key' => 'common.unknown', 'value' => 'Unknown'],
                ['key' => 'common.up', 'value' => 'Up'],
                ['key' => 'common.view_history', 'value' => 'View history'],
                ['key' => 'common.yes', 'value' => 'Yes'],

                ['key' => 'nav.warehouse', 'value' => 'Warehouse'],

                ['key' => 'warehouse.join_cp_title', 'value' => 'Join a CP'],
                ['key' => 'warehouse.join_cp_text', 'value' => 'You need to be assigned to a CP to view your warehouse.'],
                ['key' => 'warehouse.assigned_items_kicker', 'value' => 'Assigned items'],
                ['key' => 'warehouse.adena_owed', 'value' => 'Adena owed'],
                ['key' => 'warehouse.adena_paid', 'value' => 'Adena paid'],
                ['key' => 'warehouse.filter_item_placeholder', 'value' => 'Filter item...'],
                ['key' => 'warehouse.no_items_assigned', 'value' => 'You have no assigned items.'],
                ['key' => 'warehouse.return', 'value' => 'Return'],
                ['key' => 'warehouse.return_item', 'value' => 'Return Item'],
                ['key' => 'warehouse.assigned', 'value' => 'Assigned'],
                ['key' => 'warehouse.trade_screenshot_required', 'value' => 'Trade screenshot (required)'],
                ['key' => 'warehouse.toast.return_requested', 'value' => 'Return request sent.'],
                ['key' => 'warehouse.toast.return_failed', 'value' => 'Could not send the return request.'],

                ['key' => 'system.users.page_title', 'value' => 'Member Management'],
                ['key' => 'system.users.header_title', 'value' => 'Member Management'],
                ['key' => 'system.users.subtitle_admin', 'value' => 'Global Users & Balances Audit'],
                ['key' => 'system.users.subtitle_leader', 'value' => 'Balance Management & CP Audit'],
                ['key' => 'system.users.search_placeholder', 'value' => 'Search by name or email...'],
                ['key' => 'system.users.total_users', 'value' => 'Total Users'],
                ['key' => 'system.users.total_adena', 'value' => 'Total Adena (sum)'],
                ['key' => 'system.users.total_points', 'value' => 'Total Points'],
                ['key' => 'system.users.member', 'value' => 'Member'],
                ['key' => 'system.users.role_cp', 'value' => 'Role / CP'],
                ['key' => 'system.users.adena_balance', 'value' => 'Adena Balance'],
                ['key' => 'system.users.points_balance', 'value' => 'Points Balance'],
                ['key' => 'system.users.actions.manage_adena', 'value' => 'Manage Adena Balance'],
                ['key' => 'system.users.actions.edit_role_cp', 'value' => 'Edit Role/CP'],
                ['key' => 'system.users.actions.delete_user', 'value' => 'Delete User'],
                ['key' => 'system.users.actions.reactivate_user', 'value' => 'Reactivate User'],
                ['key' => 'system.users.actions.ban_user', 'value' => 'Exclude/Ban User'],
                ['key' => 'system.users.adena_movements', 'value' => 'Adena Payments / Movements'],
                ['key' => 'system.users.no_adena_movements', 'value' => 'No Adena movements.'],
                ['key' => 'system.users.action_audit', 'value' => 'Actions Audit'],
                ['key' => 'system.users.no_actions', 'value' => 'No actions recorded.'],
                ['key' => 'system.users.no_users_found', 'value' => 'No users found...'],
                ['key' => 'system.users.balance_management', 'value' => 'Balance Management'],
                ['key' => 'system.users.adena_amount', 'value' => 'Adena Amount'],
                ['key' => 'system.users.adena_amount_placeholder', 'value' => 'Ex: -1000000 to pay, 500000 to add'],
                ['key' => 'system.users.negative_values_tip', 'value' => 'Use negative values to subtract from the fund (payments)'],
                ['key' => 'system.users.reason_description', 'value' => 'Reason / Description'],
                ['key' => 'system.users.reason_placeholder', 'value' => 'Ex: Payment for Draconic Bow drop'],
                ['key' => 'system.users.trade_screenshot_required', 'value' => 'Trade screenshot (required)'],
                ['key' => 'system.users.register_transaction', 'value' => 'Register Transaction'],
                ['key' => 'system.users.reassign_user', 'value' => 'Reassign User'],
                ['key' => 'system.users.system_role', 'value' => 'System Role'],
                ['key' => 'system.users.assign_cp', 'value' => 'Assign to CP'],
                ['key' => 'system.users.no_cp_option', 'value' => 'None / Solo'],
                ['key' => 'system.users.update_assignment', 'value' => 'Update Assignment'],
                ['key' => 'system.users.swal.delete_title', 'value' => 'Delete User'],
                ['key' => 'system.users.swal.delete_text', 'value' => 'Delete {name}? This action cannot be undone.'],
                ['key' => 'system.users.swal.ban_title', 'value' => 'Exclude User'],
                ['key' => 'system.users.swal.ban_text', 'value' => 'Exclude {name}? They can be reactivated later.'],
                ['key' => 'system.users.swal.ban_confirm', 'value' => 'Exclude'],
                ['key' => 'system.users.swal.unban_title', 'value' => 'Reactivate User'],
                ['key' => 'system.users.swal.unban_text', 'value' => 'Reactivate {name}?'],
                ['key' => 'system.users.swal.unban_confirm', 'value' => 'Reactivate'],
                ['key' => 'system.users.audit.role_change', 'value' => 'Role: {from} → {to}'],
                ['key' => 'system.users.audit.cp_change', 'value' => 'CP: {from} → {to}'],
                ['key' => 'system.users.audit.updated', 'value' => 'Updated'],
                ['key' => 'system.users.audit.user_deleted', 'value' => 'User deleted'],
                ['key' => 'system.users.audit.adena_adjusted', 'value' => 'Adena adjusted ({amount})'],
                ['key' => 'system.users.audit.adena_adjusted_with_desc', 'value' => 'Adena adjusted ({amount}) · {description}'],

                ['key' => 'system.items.items_found', 'value' => 'items found'],
                ['key' => 'system.items.search_item', 'value' => 'Search Item'],
                ['key' => 'system.items.search_placeholder', 'value' => 'Ex: Angel Slayer...'],
                ['key' => 'system.items.chronicle', 'value' => 'Chronicle'],
                ['key' => 'system.items.all_chronicles', 'value' => 'All chronicles'],
                ['key' => 'system.items.grade', 'value' => 'Grade'],
                ['key' => 'system.items.all_grades', 'value' => 'All grades'],
                ['key' => 'system.items.category', 'value' => 'Category'],
                ['key' => 'system.items.all_categories', 'value' => 'All categories'],
                ['key' => 'system.items.base_points', 'value' => 'Base Points'],
                ['key' => 'system.items.edit_item', 'value' => 'Edit Item'],
                ['key' => 'system.items.details', 'value' => 'Details'],
                ['key' => 'system.items.external_id', 'value' => 'External ID'],
                ['key' => 'system.items.icon_name', 'value' => 'Icon Name'],

                ['key' => 'party.head_title', 'value' => 'Party'],
                ['key' => 'party.join.title', 'value' => 'Join a CP'],
                ['key' => 'party.join.subtitle', 'value' => 'You need to be assigned to a CP to access the party.'],
                ['key' => 'party.invite.label', 'value' => 'Invite'],
                ['key' => 'party.cp_leader', 'value' => 'CP Leader'],
                ['key' => 'party.tabs.members', 'value' => 'Members'],
                ['key' => 'party.tabs.vault', 'value' => 'Warehouse'],
                ['key' => 'party.tabs.crafting', 'value' => 'Crafting'],
                ['key' => 'party.tabs.points_settings', 'value' => 'Points'],
                ['key' => 'party.member.badge_leader', 'value' => 'Leader'],
                ['key' => 'party.member.owed', 'value' => 'Owed'],
                ['key' => 'party.member.paid', 'value' => 'Paid'],
                ['key' => 'party.member.warehouse_title', 'value' => '{name} Warehouse'],
                ['key' => 'party.member.warehouse_load_failed', 'value' => 'Could not load the warehouse.'],
                ['key' => 'party.member.warehouse_empty', 'value' => 'No items.'],
                ['key' => 'party.member.audit_title', 'value' => '{name} Audit'],
                ['key' => 'party.member.audit_load_failed', 'value' => 'Could not load the audit.'],
                ['key' => 'party.member.adena_payments_title', 'value' => 'Adena Payments / Movements'],
                ['key' => 'party.member.adena_payments_empty', 'value' => 'No Adena movements.'],
                ['key' => 'party.member.view_history', 'value' => 'View history'],
                ['key' => 'party.member.actions_audit_title', 'value' => 'Actions Audit'],
                ['key' => 'party.member.actions_audit_empty', 'value' => 'No actions recorded.'],
                ['key' => 'party.vault.title', 'value' => 'CP Warehouse'],
                ['key' => 'party.vault.subtitle', 'value' => 'CP stock and Adena.'],
                ['key' => 'party.vault.add_items', 'value' => 'Add items'],
                ['key' => 'party.vault.adena_in_warehouse', 'value' => 'Adena in warehouse'],
                ['key' => 'party.vault.adena_owed', 'value' => 'Adena owed'],
                ['key' => 'party.vault.adena_paid', 'value' => 'Adena paid'],
                ['key' => 'party.vault.filter_placeholder', 'value' => 'Filter item...'],
                ['key' => 'party.vault.empty_filtered', 'value' => 'No results.'],
                ['key' => 'party.vault.empty', 'value' => 'No items.'],
                ['key' => 'party.assign_from_warehouse', 'value' => 'Assign Item from Warehouse'],
                ['key' => 'party.in_vault', 'value' => 'In vault:'],
                ['key' => 'party.trade_screenshot_required', 'value' => 'Trade screenshot (required)'],
                ['key' => 'party.assign', 'value' => 'Assign'],
                ['key' => 'party.sell_from_warehouse', 'value' => 'Sell Item from Warehouse'],
                ['key' => 'party.total_sale', 'value' => 'Total Sale'],
                ['key' => 'party.unit_price', 'value' => 'Price / Unit'],
                ['key' => 'party.adena_destination', 'value' => 'Adena destination'],
                ['key' => 'party.cp_fund', 'value' => 'CP fund'],
                ['key' => 'party.no_split_desc', 'value' => 'Does not split to members.'],
                ['key' => 'party.split', 'value' => 'Split'],
                ['key' => 'party.split_desc', 'value' => 'Generates Adena owed per member.'],
                ['key' => 'party.split_members', 'value' => 'Split members'],
                ['key' => 'party.sale_screenshot_required', 'value' => 'Sale screenshot (required)'],
                ['key' => 'party.register_sale', 'value' => 'Register Sale'],
                ['key' => 'party.add_items_to_warehouse', 'value' => 'Add Items to Warehouse'],
                ['key' => 'party.add_adena', 'value' => 'Add Adena'],
                ['key' => 'party.lines', 'value' => 'Lines'],
                ['key' => 'party.units', 'value' => 'Units'],
                ['key' => 'party.screenshot_required', 'value' => 'Screenshot (required)'],

                ['key' => 'craft.title', 'value' => 'Crafting'],
                ['key' => 'craft.subtitle', 'value' => 'Recipes, materials and progress.'],
                ['key' => 'craft.search_placeholder', 'value' => 'Search recipe...'],
                ['key' => 'craft.search_failed', 'value' => 'Search failed.'],
                ['key' => 'craft.success', 'value' => 'success'],
                ['key' => 'craft.no_recipes', 'value' => 'No recipes.'],
                ['key' => 'craft.priority', 'value' => 'Priority: {value}'],
                ['key' => 'craft.recipe_fallback', 'value' => 'Recipe'],
                ['key' => 'craft.outputs', 'value' => 'Outputs'],
                ['key' => 'craft.output', 'value' => 'Output'],
                ['key' => 'craft.actions.craft', 'value' => 'Craft'],
                ['key' => 'craft.materials', 'value' => 'Materials'],
                ['key' => 'craft.no_materials', 'value' => 'No materials.'],
                ['key' => 'craft.material_fallback', 'value' => 'Material'],
                ['key' => 'craft.craftable', 'value' => 'Craftable'],
                ['key' => 'craft.tree.hide', 'value' => 'Hide tree'],
                ['key' => 'craft.tree.show', 'value' => 'Show tree'],
                ['key' => 'craft.tree.title', 'value' => 'Crafting tree'],
                ['key' => 'craft.tree.base_materials', 'value' => 'Base materials'],
                ['key' => 'craft.tree.empty', 'value' => 'No data.'],
                ['key' => 'craft.confirm.title', 'value' => 'Confirm crafting'],
                ['key' => 'craft.confirm.subtitle', 'value' => 'Mark this recipe as crafted?'],

                ['key' => 'party.points.title', 'value' => 'Points'],
                ['key' => 'party.points.subtitle', 'value' => 'Base points settings.'],
                ['key' => 'party.points.current', 'value' => 'Current'],
                ['key' => 'party.points.pts', 'value' => 'pts'],
                ['key' => 'party.points.hint', 'value' => 'Define base points per item.'],
            ],
        ];

        $curatedKeys = [
            'es' => [],
            'en' => [],
        ];

        foreach ($translations as $language => $items) {
            foreach ($items as $translation) {
                $curatedKeys[$language][$translation['key']] = true;

                Translation::updateOrCreate(
                    ['key' => $translation['key'], 'language' => $language],
                    ['value' => $translation['value']]
                );
            }
        }

        Translation::whereRaw('RIGHT(`key`, 1) = "." OR `key` LIKE "%..%"')->delete();

        $frontendKeys = $this->discoverFrontendTranslationKeys();
        $dbKeys = Translation::query()->distinct()->pluck('key')->map(fn ($k) => (string) $k)->all();
        $keysToEnsure = array_values(array_unique(array_merge($frontendKeys, $dbKeys)));
        $keysToEnsure = array_values(array_filter($keysToEnsure, fn ($k) => $k !== '' && ! str_ends_with($k, '.') && ! str_contains($k, '..')));
        sort($keysToEnsure);

        $existing = Translation::whereIn('language', ['es', 'en'])
            ->whereIn('key', $keysToEnsure)
            ->get(['language', 'key', 'value'])
            ->keyBy(fn ($row) => $row->language . '|' . $row->key);

        foreach (['es', 'en'] as $language) {
            foreach ($keysToEnsure as $key) {
                if (isset($curatedKeys[$language][$key])) {
                    continue;
                }

                $generatedValue = $language === 'es' ? $this->generateSpanishValue($key) : $this->generateEnglishValue($key);
                $row = $existing->get($language . '|' . $key);
                $enRow = $existing->get('en|' . $key);

                if (! $row) {
                    Translation::create([
                        'language' => $language,
                        'key' => $key,
                        'value' => $generatedValue,
                    ]);
                    continue;
                }

                $shouldUpdate = $this->shouldAutoUpdateValue((string) $row->value, $key, $language);
                if ($language === 'es' && ! $this->shouldKeepSameAcrossLanguages($key) && $enRow && trim((string) $row->value) === trim((string) $enRow->value)) {
                    $shouldUpdate = true;
                }

                if ($shouldUpdate) {
                    Translation::where('language', $language)->where('key', $key)->update(['value' => $generatedValue]);
                }
            }
        }
    }

    private function shouldAutoUpdateValue(string $existingValue, string $key, string $language): bool
    {
        $trimmed = trim($existingValue);

        if ($trimmed === '') {
            return true;
        }

        if ($trimmed === $key) {
            return true;
        }

        if ($language === 'es' && $trimmed === $this->generateEnglishValue($key)) {
            return true;
        }

        return false;
    }

    private function shouldKeepSameAcrossLanguages(string $key): bool
    {
        if (str_starts_with($key, 'chronicles.')) {
            return true;
        }

        return in_array($key, [
            'app.name',
            'app.tagline',
            'common.adena',
            'common.cp',
            'common.id',
            'common.na',
            'common.no',
            'common.ok',
            'common.pts',
            'lang.en',
            'lang.es',
        ], true);
    }

    private function discoverFrontendTranslationKeys(): array
    {
        $root = resource_path('js');
        if (! is_dir($root)) {
            return [];
        }

        $keys = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $path = $file->getPathname();
            if (! preg_match('/\.(vue|js|ts)$/', $path)) {
                continue;
            }

            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            if (preg_match_all('/\$t\(\s*[\'"]([A-Za-z0-9_.-]+)[\'"]/', $content, $matches)) {
                foreach ($matches[1] as $key) {
                    if ($key === '' || str_ends_with($key, '.') || str_contains($key, '..')) {
                        continue;
                    }
                    $keys[$key] = true;
                }
            }
        }

        $unique = array_keys($keys);
        sort($unique);

        return $unique;
    }

    private function generateEnglishValue(string $key): string
    {
        $lastSegment = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;
        $normalized = str_replace(['_', '-'], ' ', $lastSegment);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? $normalized;

        $words = $normalized === '' ? [] : explode(' ', $normalized);
        $out = [];

        foreach ($words as $word) {
            $lower = strtolower($word);

            if ($lower === 'cp') {
                $out[] = 'CP';
                continue;
            }

            if ($lower === 'cps') {
                $out[] = 'CPs';
                continue;
            }

            if ($lower === 'id') {
                $out[] = 'ID';
                continue;
            }

            if ($lower === 'ok') {
                $out[] = 'OK';
                continue;
            }

            if ($lower === 'na') {
                $out[] = 'N/A';
                continue;
            }

            if ($lower === 'pts') {
                $out[] = 'pts';
                continue;
            }

            if ($lower === 'adena') {
                $out[] = 'Adena';
                continue;
            }

            if (preg_match('/^[a-z]+\d+$/i', $word)) {
                $out[] = strtoupper($word);
                continue;
            }

            $out[] = ucfirst($lower);
        }

        return $out === [] ? $key : implode(' ', $out);
    }

    private function generateSpanishValue(string $key): string
    {
        $special = [
            'auth.login.title' => 'Entrar',
            'auth.login.subtitle' => 'Accede con tu cuenta',
            'auth.login.submit' => 'Entrar',
            'auth.login.remember' => 'Recordarme',
            'auth.login.forgot' => '¿Olvidaste tu contraseña?',

            'auth.register.title' => 'Registro',
            'auth.register.subtitle' => 'Crea tu cuenta',
            'auth.register.submit' => 'Registrarme',
            'auth.register.have_account' => 'Ya tengo cuenta',
            'auth.register.invite_code' => 'Código de invitación',
            'auth.register.character_name' => 'Nombre de personaje',

            'auth.forgot.title' => 'Recuperar contraseña',
            'auth.forgot.description' => 'Te enviaremos un enlace para restablecer la contraseña.',
            'auth.forgot.submit' => 'Enviar enlace',

            'auth.reset.title' => 'Restablecer contraseña',
            'auth.reset.submit' => 'Restablecer',

            'auth.confirm.title' => 'Confirmar contraseña',
            'auth.confirm.description' => 'Confirma tu contraseña para continuar.',
            'auth.confirm.submit' => 'Confirmar',

            'auth.verify.title' => 'Verificar email',
            'auth.verify.description' => 'Gracias por registrarte. Antes de empezar, verifica tu email.',
            'auth.verify.resend' => 'Reenviar verificación',
            'auth.verify.sent' => 'Enlace de verificación enviado.',

            'common.confirmed' => 'Confirmado',
            'common.error_occurred' => 'Ha ocurrido un error.',
            'common.errors' => 'Errores',
            'common.idle' => 'Inactivo',
            'common.no_data' => 'Sin datos.',
            'common.no_data_yet' => 'Aún no hay datos.',
            'common.no_recent_records' => 'Sin registros recientes.',
            'common.saved' => 'Guardado',

            'alerts.none' => 'Sin alertas.',
            'alerts.mark_read' => 'Marcar como leído',
            'alerts.mark_all' => 'Marcar todo',

            'cp.activity.no_data' => 'Sin datos.',
            'cp.live_status' => 'Estado',
            'cp.metrics.adena_paid' => 'Adena pagada',
            'cp.tasks.bosses' => 'Bosses',
            'cp.tasks.dailies' => 'Diarias',
            'cp.tasks.review_warehouse' => 'Revisar Warehouse',
            'cp.week.top_activity' => 'Top actividad',

            'craft.title' => 'Crafting',

            'form.chronicle' => 'Crónica',
            'form.contact_email_optional' => 'Email de contacto (opcional)',
            'form.email_optional' => 'Email (opcional)',
            'form.subject' => 'Asunto',

            'loot.accept' => 'Aceptar',
            'loot.accept_return' => 'Aceptar devolución',
            'loot.action' => 'Acción',
            'loot.adena_distribution' => 'Distribución de Adena',
            'loot.attendees' => 'Asistentes',
            'loot.distribution' => 'Distribución',
            'loot.distribute_attendees' => 'Repartir a asistentes',
            'loot.each_receives_total' => 'Cada uno recibe (total)',
            'loot.evidence' => 'Prueba',
            'loot.grade' => 'Grado',
            'loot.items_acquired' => 'Items obtenidos',
            'loot.modal.activity_type' => 'Tipo de actividad',
            'loot.modal.item_search_placeholder' => 'Buscar item...',
            'loot.modal.quantity' => 'Cantidad',
            'loot.modal.upload_click' => 'Hacer clic para subir',
            'loot.no_attendees' => 'Sin asistentes.',
            'loot.no_capture' => 'Sin captura.',
            'loot.no_cp_desc' => 'Necesitas estar asignado a una CP.',
            'loot.no_results' => 'Sin resultados.',
            'loot.no_screenshot' => 'Falta captura.',
            'loot.note' => 'Nota',
            'loot.origin' => 'Origen',
            'loot.registered_by' => 'Registrado por',
            'loot.reported_by' => 'Reportado por',
            'loot.resolve_loot_session' => 'Resolver sesión de loot',
            'loot.search_placeholder' => 'Buscar...',
            'loot.session' => 'Sesión',
            'loot.session_attendees' => 'Asistentes de la sesión',
            'loot.sort.newest' => 'Más nuevos',
            'loot.sort.oldest' => 'Más antiguos',
            'loot.total' => 'Total',
            'loot.type' => 'Tipo',

            'member.last_7_days' => 'Últimos 7 días',
            'member.party_status' => 'Estado de party',
            'member.summary.items' => 'Items',
            'member.summary.owed' => 'A deber',
            'member.summary.paid' => 'Pagado',
        ];

        if (isset($special[$key])) {
            return $special[$key];
        }

        $value = $this->generateEnglishValue($key);

        $dictionary = [
            'actions' => 'Acciones',
            'add' => 'Añadir',
            'admin' => 'Admin',
            'all' => 'Todo',
            'allow' => 'Permitir',
            'approve' => 'Aprobar',
            'audit' => 'Auditoría',
            'balance' => 'Saldo',
            'cancel' => 'Cancelar',
            'close' => 'Cerrar',
            'confirm' => 'Confirmar',
            'confirm' => 'Confirmar',
            'copy' => 'Copiar',
            'create' => 'Crear',
            'created' => 'Creado',
            'dashboard' => 'Panel',
            'delete' => 'Eliminar',
            'details' => 'Detalle',
            'description' => 'Descripción',
            'edit' => 'Editar',
            'email' => 'Email',
            'error' => 'Error',
            'excluded' => 'Excluido',
            'event' => 'Evento',
            'events' => 'Eventos',
            'failed' => 'Fallido',
            'forgot' => 'Olvidé',
            'history' => 'Histórico',
            'hint' => 'Info',
            'home' => 'Inicio',
            'image' => 'Imagen',
            'invite' => 'Invitación',
            'item' => 'Ítem',
            'items' => 'Items',
            'join' => 'Unirse',
            'kpis' => 'KPIs',
            'language' => 'Idioma',
            'leader' => 'Líder',
            'link' => 'Link',
            'loading' => 'Cargando',
            'login' => 'Entrar',
            'logout' => 'Salir',
            'loot' => 'Loot',
            'member' => 'Miembro',
            'members' => 'Miembros',
            'manage' => 'Gestionar',
            'mark' => 'Marcar',
            'message' => 'Mensaje',
            'more' => 'Más',
            'name' => 'Nombre',
            'new' => 'Nuevo',
            'none' => 'Ninguno',
            'notifications' => 'Notificaciones',
            'no' => 'No',
            'notes' => 'Notas',
            'ok' => 'OK',
            'open' => 'Abrir',
            'password' => 'Contraseña',
            'pending' => 'Pendiente',
            'points' => 'Puntos',
            'profile' => 'Perfil',
            'read' => 'Leído',
            'register' => 'Registro',
            'reject' => 'Rechazar',
            'report' => 'Reporte',
            'reports' => 'Reportes',
            'request' => 'Solicitud',
            'requests' => 'Solicitudes',
            'resend' => 'Reenviar',
            'save' => 'Guardar',
            'send' => 'Enviar',
            'sending' => 'Enviando',
            'server' => 'Servidor',
            'sessions' => 'Sesiones',
            'settings' => 'Configuración',
            'subtitle' => 'Subtítulo',
            'success' => 'Éxito',
            'support' => 'Soporte',
            'theme' => 'Tema',
            'title' => 'Título',
            'total' => 'Total',
            'translations' => 'Traducciones',
            'translation' => 'Traducción',
            'update' => 'Actualizar',
            'updated' => 'Actualizado',
            'user' => 'Usuario',
            'users' => 'Usuarios',
            'verify' => 'Verificar',
            'view' => 'Ver',
            'warehouse' => 'Warehouse',
            'vault' => 'Vault',
            'yes' => 'Sí',
            'to' => 'a',
            'of' => 'de',
            'and' => 'y',
            'my' => 'mi',
            'your' => 'tu',
        ];

        $words = preg_split('/\s+/', trim($value)) ?: [];
        if ($words === []) {
            return $key;
        }

        $out = [];
        foreach ($words as $index => $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $word) ?? $word;
            $lower = mb_strtolower($clean);

            if ($lower === 'cp') {
                $out[] = 'CP';
                continue;
            }

            if ($lower === 'cps') {
                $out[] = 'CPs';
                continue;
            }

            if ($lower === 'adena') {
                $out[] = 'Adena';
                continue;
            }

            if (isset($dictionary[$lower])) {
                $translated = $dictionary[$lower];
                $out[] = $index === 0 ? $translated : $translated;
                continue;
            }

            $out[] = $word;
        }

        return implode(' ', $out);
    }
}
