<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        'landing.meta.title' => [
            'es' => 'Descargar — AdenaLedgerStats para Lu4',
            'en' => 'Download — AdenaLedgerStats for Lu4',
        ],
        'landing.nav.features'  => ['es' => 'Funciones',            'en' => 'Features'],
        'landing.nav.changelog' => ['es' => 'Cambios',              'en' => 'Changelog'],

        'landing.hero.badge' => [
            'es' => 'Solo para el servidor privado Lu4',
            'en' => 'Only for the Lu4 private server',
        ],
        'landing.hero.title_line1' => ['es' => 'Overlay y tracker para', 'en' => 'Overlay & tracker for'],
        'landing.hero.title_line2' => ['es' => 'en Lu4',                  'en' => 'on Lu4'],
        'landing.hero.subtitle' => [
            'es' => 'Overlay de HP/MP/CP, lector OCR del chat, estadísticas de sesión, muertes, XP/h, adena/h, control de soulshots y un HUD oscuro que no estorba. Un único ejecutable, sin instalador, sin modificar archivos del juego.',
            'en' => 'HP/MP/CP overlay, OCR-based chat parser, session stats, deaths, XP/h, adena/h, soulshot tracking and a clean dark HUD that stays out of your way. Single binary, no installer, no game-file modification.',
        ],

        'landing.download.btn' => [
            'es' => 'Descargar {version}',
            'en' => 'Download {version}',
        ],
        'landing.download.critical_warning' => [
            'es' => 'Actualización crítica — las versiones anteriores ya no están soportadas.',
            'en' => 'Critical update — previous versions are no longer supported.',
        ],
        'landing.download.no_release' => [
            'es' => 'Aún no hay una versión publicada.',
            'en' => 'No published release yet.',
        ],

        'landing.preview.ss_used' => ['es' => 'SS gastados', 'en' => 'SS used'],
        'landing.preview.deaths'  => ['es' => 'Muertes',     'en' => 'Deaths'],

        'landing.disclaimer.label' => ['es' => 'Importante.', 'en' => 'Important.'],
        'landing.disclaimer.text' => [
            'es' => 'Este software solo funciona en el servidor privado Lu4. No está afiliado ni respaldado por NCsoft. El uso de esta herramienta es bajo tu responsabilidad — ejecutar software de terceros junto a Lineage 2 puede ir contra los términos de servicio del servidor. El OCR se ejecuta localmente sobre píxeles de pantalla; no se lee ni modifica memoria del juego.',
            'en' => "This software only works on the Lu4 private server. It is not affiliated with or endorsed by NCsoft. Use of this tool is at your own responsibility — running third-party software alongside Lineage 2 may be against your server's terms of service. The OCR runs locally on screen pixels only; no game memory is read or modified.",
        ],

        'landing.features.title' => ['es' => 'Qué incluye', 'en' => "What's in the box"],
        'landing.features.overlay.title' => ['es' => 'Overlay siempre activo', 'en' => 'Always-on overlay'],
        'landing.features.overlay.text' => [
            'es' => 'HUD compacto con HP / MP / CP, estado de soulshots, XP/h, adena/h y registro del último hit. Transparente y reposicionable.',
            'en' => 'Compact HUD with HP / MP / CP, soulshot status, XP/h, adena/h and last hit tracker. Transparent, repositionable.',
        ],
        'landing.features.ocr.title' => ['es' => 'Lectura por OCR', 'en' => 'OCR-based parsing'],
        'landing.features.ocr.text' => [
            'es' => 'Lee barras y chat desde píxeles con RapidOCR (ONNX) y Tesseract de fallback. Procesos paralelos para que el juego siga fluido.',
            'en' => 'Reads bars and chat from pixels using RapidOCR (ONNX) with Tesseract fallback. Multi-process pool keeps the game responsive.',
        ],
        'landing.features.stats.title' => ['es' => 'Estadísticas de sesión', 'en' => 'Session stats'],
        'landing.features.stats.text' => [
            'es' => 'Historial local en SQLite de cada sesión: kills, muertes, ítems obtenidos, daño hecho/recibido. Se reanuda al reiniciar el juego.',
            'en' => 'Local SQLite history of every session: kills, deaths, items obtained, damage in/out. Resumable on game restart.',
        ],
        'landing.features.autoupdate.title' => ['es' => 'Auto-actualización', 'en' => 'Auto-updates'],
        'landing.features.autoupdate.text' => [
            'es' => 'El programa busca nuevas versiones al arrancar y se actualiza con un click. Sin reinstalar, sin Steam, sin complicaciones.',
            'en' => 'The bot checks for new versions on launch and updates itself with one click. No reinstall, no Steam, no fuss.',
        ],
        'landing.features.tickets.title' => ['es' => 'Tickets de soporte integrados', 'en' => 'In-app support tickets'],
        'landing.features.tickets.text' => [
            'es' => '¿Encontraste un bug? Abre un ticket desde el overlay; logs y configuración se adjuntan automáticamente (limpios de datos sensibles).',
            'en' => 'Hit a bug? Open a ticket from the overlay; logs and settings are attached automatically (scrubbed for privacy).',
        ],
        'landing.features.privacy.title' => ['es' => 'Privacidad ante todo', 'en' => 'Privacy first'],
        'landing.features.privacy.text' => [
            'es' => 'Nombres de personaje y chat con nicks se eliminan antes de enviar cualquier telemetría opcional. Opt-in, opt-out o totalmente offline.',
            'en' => 'Character names and chat with nicks are stripped before any optional telemetry leaves your machine. Opt-in, opt-out, or fully offline.',
        ],

        'landing.changelog.title'    => ['es' => 'Cambios recientes',              'en' => 'Recent changes'],
        'landing.changelog.subtitle' => [
            'es' => 'Historial del software de escritorio (AdenaLedgerStats).',
            'en' => 'Changelog of the desktop software (AdenaLedgerStats).',
        ],

        'landing.footer.tagline' => [
            'es' => 'AdenaLedgerStats — para la comunidad de Lu4. No afiliado a NCsoft.',
            'en' => 'AdenaLedgerStats — for the Lu4 community. Not affiliated with NCsoft.',
        ],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                $exists = DB::table('translations')
                    ->where('key', $key)
                    ->where('language', $lang)
                    ->exists();

                if (! $exists) {
                    $rows[] = [
                        'language'   => $lang,
                        'key'        => $key,
                        'value'      => $value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (! empty($rows)) {
            DB::table('translations')->insert($rows);
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
