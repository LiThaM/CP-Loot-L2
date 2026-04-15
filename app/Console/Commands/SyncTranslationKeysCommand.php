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
            Translation::query()->insert($chunk);
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
