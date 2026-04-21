<?php

namespace App\Console\Commands;

use App\Contexts\System\Domain\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SyncTranslationKeysCommand extends Command
{
    protected $signature = 'translations:sync-keys
        {--dry-run : Solo muestra qué faltaría crear, sin escribir en DB}
        {--paths=* : Paths relativos a escanear (por defecto resources/js,resources/views,app)}
        {--languages=en,es : Idiomas separados por coma}
        {--apply-overrides : Aplica textos corregidos para keys conocidas (upsert en DB)}';

    protected $description = 'Escanea t()/\$t() en el código, detecta keys faltantes y las crea en translations.';

    public function handle(): int
    {
        $paths = $this->option('paths');
        if (! is_array($paths) || count($paths) === 0) {
            $paths = ['resources/js', 'resources/views', 'app'];
        }

        $languages = collect(explode(',', (string) $this->option('languages')))
            ->map(fn ($lang) => strtolower(trim((string) $lang)))
            ->filter()
            ->unique()
            ->values();

        if ($languages->isEmpty()) {
            $this->error('No se recibieron idiomas válidos en --languages.');

            return self::FAILURE;
        }

        $keys = $this->collectTranslationKeys($paths);
        if (empty($keys)) {
            $this->warn('No se detectaron keys con t()/\$t() en los paths indicados.');

            return self::SUCCESS;
        }

        $existing = Translation::query()
            ->whereIn('key', $keys)
            ->get(['language', 'key', 'value'])
            ->groupBy('key')
            ->map(fn ($rows) => $rows->keyBy(fn ($row) => strtolower((string) $row->language)));

        $missing = [];
        foreach ($keys as $key) {
            $rowByLang = $existing->get($key, collect());
            foreach ($languages as $language) {
                if ($rowByLang->has($language)) {
                    continue;
                }
                $missing[] = [
                    'language' => $language,
                    'key' => $key,
                    'value' => $this->buildTranslationValue($key, $language, $rowByLang),
                ];
            }
        }

        $this->info('Keys detectadas: '.count($keys));
        $this->line('Idiomas objetivo: '.$languages->join(', '));
        $this->line('Registros faltantes: '.count($missing));

        $applyOverrides = (bool) $this->option('apply-overrides');
        $overrideRows = $applyOverrides ? $this->buildOverrideRows($languages) : [];

        if ($this->option('dry-run')) {
            if ($applyOverrides && count($overrideRows) > 0) {
                $this->line('Overrides a aplicar: '.count($overrideRows));
                $this->table(
                    ['language', 'key', 'value'],
                    array_slice($overrideRows, 0, 30)
                );
                if (count($overrideRows) > 30) {
                    $this->line('... (mostrando 30 de '.count($overrideRows).')');
                }
            }

            $this->table(
                ['language', 'key', 'value'],
                array_slice($missing, 0, 50)
            );
            if (count($missing) > 50) {
                $this->line('... (mostrando 50 de '.count($missing).')');
            }
            $this->comment('Dry-run activo: no se insertó nada.');

            return self::SUCCESS;
        }

        if ($applyOverrides && count($overrideRows) > 0) {
            $now = now();
            $payload = array_map(function (array $row) use ($now) {
                return [
                    'language' => $row['language'],
                    'key' => $row['key'],
                    'value' => $row['value'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $overrideRows);

            foreach (array_chunk($payload, 500) as $chunk) {
                Translation::query()->upsert($chunk, ['language', 'key'], ['value', 'updated_at']);
            }

            $this->info('Overrides aplicados: '.count($payload));
        }

        if (count($missing) === 0) {
            $this->info('No hay faltantes. DB ya está sincronizada para esos idiomas.');

            return self::SUCCESS;
        }

        $now = now();
        $payload = array_map(function (array $row) use ($now) {
            return [
                'language' => $row['language'],
                'key' => $row['key'],
                'value' => $row['value'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $missing);

        foreach (array_chunk($payload, 500) as $chunk) {
            Translation::query()->upsert($chunk, ['language', 'key'], ['value', 'updated_at']);
        }

        $this->info('Sincronización completada. Registros creados: '.count($payload));

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $paths
     * @return array<int, string>
     */
    private function collectTranslationKeys(array $paths): array
    {
        $extensions = ['php', 'vue', 'js', 'ts', 'jsx', 'tsx'];
        $ignoreStarts = [
            base_path('vendor'),
            base_path('node_modules'),
            base_path('public/build'),
            base_path('storage'),
            base_path('bootstrap/cache'),
        ];

        $keys = [];
        foreach ($paths as $relativePath) {
            $absolute = base_path(trim((string) $relativePath, '/'));
            if (! is_dir($absolute)) {
                $this->warn("Path no encontrado o no es carpeta: {$relativePath}");
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolute, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                $filePath = (string) $fileInfo->getPathname();
                $ext = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
                if (! in_array($ext, $extensions, true)) {
                    continue;
                }

                $skip = false;
                foreach ($ignoreStarts as $prefix) {
                    if (str_starts_with($filePath, $prefix)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                $content = @file_get_contents($filePath);
                if (! is_string($content) || $content === '') {
                    continue;
                }

                foreach ($this->extractKeysFromContent($content) as $key) {
                    $keys[$key] = true;
                }
            }
        }

        $all = array_keys($keys);
        sort($all);

        return $all;
    }

    /**
     * @return array<int, string>
     */
    private function extractKeysFromContent(string $content): array
    {
        $patterns = [
            '/\$t\(\s*([\'"])([^\'"]+)\1/u',
            '/(?<![\w$])t\(\s*([\'"])([^\'"]+)\1/u',
        ];

        $found = [];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $key = trim((string) ($match[2] ?? ''));
                if ($this->isValidTranslationKey($key)) {
                    $found[$key] = true;
                }
            }
        }

        return array_keys($found);
    }

    private function isValidTranslationKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }
        if (str_contains($key, '${')) {
            return false;
        }
        if (str_ends_with($key, '.')) {
            return false;
        }

        return true;
    }

    private function buildTranslationValue(string $key, string $language, $rowsByLanguage): string
    {
        $fallbackLang = $language === 'es' ? 'en' : 'es';
        $fallbackValue = (string) optional($rowsByLanguage->get($fallbackLang))->value;
        if ($fallbackValue !== '') {
            return $fallbackValue;
        }

        $base = $this->humanizeKey($key);

        if ($language === 'es') {
            return $this->translateHumanizedToEs($base);
        }

        return $base;
    }

    private function humanizeKey(string $key): string
    {
        $last = (string) Str::of($key)->afterLast('.');
        $last = str_replace(['_', '-'], ' ', $last);
        $last = (string) Str::of($last)->squish();
        if ($last === '') {
            $last = str_replace(['.', '_', '-'], ' ', $key);
            $last = (string) Str::of($last)->squish();
        }

        return Str::title($last);
    }

    private function translateHumanizedToEs(string $text): string
    {
        $map = [
            'Add' => 'Añadir',
            'Item' => 'Ítem',
            'Items' => 'Ítems',
            'Search' => 'Buscar',
            'Load' => 'Cargar',
            'More' => 'Más',
            'Save' => 'Guardar',
            'Cancel' => 'Cancelar',
            'Member' => 'Miembro',
            'Members' => 'Miembros',
            'Warehouse' => 'Warehouse',
            'Buy' => 'Comprar',
            'Sell' => 'Vender',
            'Assign' => 'Asignar',
            'Distribution' => 'Distribución',
            'Attendees' => 'Asistentes',
            'Points' => 'Puntos',
            'Total' => 'Total',
            'Report' => 'Reporte',
            'Session' => 'Sesión',
            'History' => 'Historial',
            'Pending' => 'Pendiente',
            'Wishlist' => 'Wishlist',
            'No' => 'Sin',
            'Results' => 'Resultados',
            'Evidence' => 'Evidencia',
            'Origin' => 'Origen',
            'Note' => 'Nota',
            'Notes' => 'Notas',
            'Confirm' => 'Confirmar',
            'Reject' => 'Rechazar',
            'Accept' => 'Aceptar',
            'Title' => 'Título',
            'Subtitle' => 'Subtítulo',
            'Cp' => 'CP',
            'Vault' => 'Vault',
            'Adena' => 'Adena',
        ];

        $parts = preg_split('/\s+/', trim($text)) ?: [];
        $translated = array_map(function (string $word) use ($map) {
            return $map[$word] ?? $word;
        }, $parts);

        return trim(implode(' ', $translated));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function translationOverrides(): array
    {
        return [
            'es' => [
                'loot.adena_split_title' => 'Desglose de Adena',
                'loot.adena_total' => 'Adena total',
                'loot.adena_each' => 'Por miembro',
                'loot.adena_remainder_to_cp' => 'Resto a la CP: {amount}',
                'loot.adena_to_cp_title' => 'Adena para la CP',
                'loot.adena_to_cp_desc' => 'Adena que entra al warehouse de la CP: {amount}',

                'party.assign_adena_offset_title' => 'Cobro de Adena',
                'party.assign_member_owed' => 'Adena pendiente',
                'party.assign_adena_offset_toggle' => 'Cobrar de lo pendiente',
                'party.assign_adena_offset_amount' => 'Adena a cobrar',
                'party.assign_adena_offset_max' => 'Cobrar todo',

                'party.no_split_desc' => 'No hay split: la Adena entra al warehouse de la CP.',
                'party.cp_receives_adena' => 'Adena a la CP: {amount}',
                'party.remainder_to_cp' => 'Resto a la CP: {amount}',
                'common.cp' => 'Const Party',
                'common.optional' => '(opcional)',
 
                'craft.recipe_fallback' => 'RECETA',
                'craft.material_fallback' => 'Material',
                'craft.craftable' => 'Crafteable',
                'craft.tree.title' => 'Estructura de Crafteo',
                'craft.tree.base_materials' => 'Materiales Base Totales',
                'craft.tree.show' => 'Ver Árbol',
                'craft.tree.hide' => 'Ocultar Árbol',
                'common.missing' => 'Faltante',
                'common.loading' => 'Cargando...',
                'common.item' => 'Ítem',

                'welcome.seo.description' => 'Adena Ledger: La herramienta definitiva para la gestión de Const Parties en Lineage 2. Administra repartos de loot, subastas, adena y crafting con analíticas avanzadas.',
                
                'membership.pending.title' => 'Esperando Aprobación',
                'membership.pending.status_label' => 'Estado: Pendiente',
                'membership.pending.header' => 'Esperando Aprobación',
                'membership.pending.message' => 'Tu solicitud para unirte a {cp} está siendo procesada por el líder.',
                'membership.pending.tip' => 'Estamos revisando tu perfil. Serás notificado una vez se tome una decisión.',
                'nav.support' => 'Soporte',
                'party.donation.btn_label' => 'Donar a CP',
                'party.donation.btn_title' => 'Donar adena adeudada al fondo de la CP',
                'party.donation.modal_title' => 'Donar al fondo de la CP',
                'party.donation.modal_text' => '¿Cuánta adena quieres donar al fondo común? Se restará de lo que se te debe. Máximo: {max}',
                'party.donation.success' => '¡Gracias por tu donación! El saldo ha sido ajustado.',
                'cp.metrics.adena_net' => 'Saldo Neto',
                'cp.hero.title' => '¡Hola, {name}!',
                'cp.hero.subtitle' => 'Gestiona el flujo de bienes y finanzas de tu Const Party.',
                'common.liquid_assets' => 'Activos Líquidos',
                'common.pending_debt' => 'Deuda Pendiente',
                'common.total_distributed' => 'Total Repartido',
                'common.unknown_server' => 'Servidor Desconocido',
                'common.save_changes' => 'Guardar Cambios',
                'nav.profile' => 'Mi Perfil',
                'nav.donations' => 'Donaciones',
                'nav.logout' => 'Cerrar Sesión',
                'party.tabs.settings' => 'Ajustes',
                'party.tabs.vault' => 'Warehouse',
                'party.tabs.crafting' => 'Crafting',
                'party.tabs.points_settings' => 'Puntos',
                'party.members_count' => 'Miembros',
                'party.invite.title' => 'Código de Invitación',
                'party.invite.description' => 'Comparte este link con los nuevos miembros para que se unan a tu CP.',
                'party.invite.copy_btn' => 'Copiar Link',
                'cp.settings.success' => 'Ajustes de la Const Party actualizados correctamente.',
                'cp.settings.logo_section' => 'Identidad Visual',
                'cp.settings.logo_tip' => 'Formatos: JPG, PNG o WEBP. Máximo: 3MB.',
                'cp.settings.general_title' => 'Ajustes Generales',
                'cp.settings.chronicle_locked_tip' => 'La crónica no puede modificarse una vez creada la CP.',
                'nav.impersonation.active' => 'Modo Impersonación Activo',
                'nav.impersonation.viewing_as' => 'Estás viendo la plataforma como {name}',
                'nav.impersonation.leave' => 'Volver a mi cuenta',
                'welcome.hero.badge' => 'Herramienta Premium para Lineage 2',
                'welcome.hero.cta.learn_more' => 'Saber más',
                'welcome.features.title' => 'Funcionalidades del Sistema',
                'profile.page.title' => 'Perfil',
                'profile.page.subtitle' => 'Preferencias de cuenta',
                'profile.password.title' => 'Cambiar Contraseña',
                'profile.delete.title' => 'Eliminar Cuenta',
                'profile.preferences.title' => 'Preferencias de Interfaz',
                'profile.preferences.description' => 'Personaliza cómo se ve y se comporta la aplicación para ti.',
                'profile.preferences.theme' => 'Tema',
                'profile.preferences.language' => 'Idioma',
                'profile.preferences.theme_system' => 'Sistema',
                'profile.preferences.theme_light' => 'Claro',
                'profile.preferences.theme_dark' => 'Oscuro',
                'profile.preferences.lang_system' => 'Auto',
            ],
            'en' => [
                'loot.adena_split_title' => 'Adena Breakdown',
                'loot.adena_total' => 'Total Adena',
                'loot.adena_each' => 'Per member',
                'loot.adena_remainder_to_cp' => 'Remainder to CP: {amount}',
                'loot.adena_to_cp_title' => 'Adena to CP',
                'loot.adena_to_cp_desc' => 'Adena goes to the CP warehouse: {amount}',

                'party.assign_adena_offset_title' => 'Adena charge',
                'party.assign_member_owed' => 'Owed Adena',
                'party.assign_adena_offset_toggle' => 'Charge owed',
                'party.assign_adena_offset_amount' => 'Adena to charge',
                'party.assign_adena_offset_max' => 'Charge all',

                'party.no_split_desc' => 'No split: Adena goes to the CP warehouse.',
                'party.cp_receives_adena' => 'Adena to CP: {amount}',
                'party.remainder_to_cp' => 'Remainder to CP: {amount}',
 
                'craft.recipe_fallback' => 'RECIPE',
                'craft.material_fallback' => 'Material',
                'craft.craftable' => 'Craftable',
                'craft.tree.title' => 'Crafting Tree',
                'craft.tree.base_materials' => 'Total Base Materials',
                'craft.tree.show' => 'Show Tree',
                'craft.tree.hide' => 'Hide Tree',
                'common.missing' => 'Missing',
                'common.loading' => 'Loading...',
                'common.item' => 'Item',

                'welcome.seo.description' => 'Adena Ledger: The ultimate tool for Lineage 2 Const Party management. Manage loot distribution, auctions, adena, and crafting with advanced analytics.',
                
                'membership.pending.title' => 'Awaiting Approval',
                'membership.pending.status_label' => 'Status: Pending',
                'membership.pending.header' => 'Awaiting Approval',
                'membership.pending.message' => 'Your request to join {cp} is being processed by the leader.',
                'membership.pending.tip' => 'We are reviewing your profile. You will be notified once a decision is made.',
                'nav.support' => 'Support',
                'party.donation.btn_label' => 'Donate to CP',
                'party.donation.btn_title' => 'Donate owed adena to the CP fund',
                'party.donation.modal_title' => 'Donate to CP Fund',
                'party.donation.modal_text' => 'How much adena do you want to donate to the common fund? It will be deducted from what is owed to you. Maximum: {max}',
                'party.donation.success' => 'Thank you for your donation! Your balance has been adjusted.',
                'cp.metrics.adena_net' => 'Net Balance',
                'cp.hero.title' => 'Welcome, {name}!',
                'cp.hero.subtitle' => 'Manage the flow of goods and finances for your Const Party.',
                'common.liquid_assets' => 'Liquid Assets',
                'common.pending_debt' => 'Pending Debt',
                'common.total_distributed' => 'Total Distributed',
                'common.unknown_server' => 'Unknown Server',
                'common.save_changes' => 'Save Changes',
                'nav.profile' => 'My Profile',
                'nav.donations' => 'Donations',
                'nav.logout' => 'Log Out',
                'party.tabs.settings' => 'Settings',
                'party.tabs.vault' => 'Warehouse',
                'party.tabs.crafting' => 'Crafting',
                'party.tabs.points_settings' => 'Points',
                'party.members_count' => 'Members',
                'party.invite.title' => 'Invitation Code',
                'party.invite.description' => 'Share this link with new members so they can join your CP.',
                'party.invite.copy_btn' => 'Copy Link',
                'cp.settings.success' => 'Const Party settings updated successfully.',
                'cp.settings.logo_section' => 'Visual Identity',
                'cp.settings.logo_tip' => 'Formats: JPG, PNG or WEBP. Max: 3MB.',
                'cp.settings.general_title' => 'General Settings',
                'cp.settings.chronicle_locked_tip' => 'Chronicle cannot be changed once the CP is created.',
                'nav.impersonation.active' => 'Impersonation Mode Active',
                'nav.impersonation.viewing_as' => 'You are viewing the platform as {name}',
                'nav.impersonation.leave' => 'Back to my account',
                'welcome.hero.badge' => 'Premium Lineage 2 Tool',
                'welcome.hero.cta.learn_more' => 'Learn More',
                'welcome.features.title' => 'System Features',
                'profile.page.title' => 'Profile',
                'profile.page.subtitle' => 'Account preferences',
                'profile.password.title' => 'Change Password',
                'profile.delete.title' => 'Delete Account',
                'profile.preferences.title' => 'Interface Preferences',
                'profile.preferences.description' => 'Customize how the application looks and behaves for you.',
                'profile.preferences.theme' => 'Theme',
                'profile.preferences.language' => 'Language',
                'profile.preferences.theme_system' => 'System',
                'profile.preferences.theme_light' => 'Light',
                'profile.preferences.theme_dark' => 'Dark',
                'profile.preferences.lang_system' => 'Auto',
            ],
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, string> $languages
     * @return array<int, array{language:string,key:string,value:string}>
     */
    private function buildOverrideRows($languages): array
    {
        $all = $this->translationOverrides();
        $rows = [];

        foreach ($languages as $lang) {
            $map = $all[$lang] ?? [];
            foreach ($map as $key => $value) {
                $rows[] = [
                    'language' => $lang,
                    'key' => $key,
                    'value' => $value,
                ];
            }
        }

        return $rows;
    }
}
