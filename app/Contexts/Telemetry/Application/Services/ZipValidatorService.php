<?php

namespace App\Contexts\Telemetry\Application\Services;

use RuntimeException;
use ZipArchive;

class ZipValidatorService
{
    public const MAX_RATIO = 100;

    /**
     * Itera entries de un ZIP de forma segura (sin extractTo). Para cada entry
     * llama $perEntry($name, $size, $callable) — $callable() devuelve los bytes
     * del PNG ya leídos. Si el callback devuelve un string lo trata como motivo
     * de rechazo y lo acumula. Si devuelve true se considera aceptado.
     *
     * @return array{accepted:int,rejected:int,reasons:array<int,string>}
     */
    public function iterate(string $localZipPath, int $maxEntries, int $maxUncompressedTotalBytes, callable $perEntry): array
    {
        $zip = new ZipArchive();
        $opened = $zip->open($localZipPath, ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new RuntimeException('Cannot open uploaded ZIP (error code '.$opened.').');
        }

        $accepted = 0;
        $rejected = 0;
        $reasons = [];
        $uncompressedSeen = 0;

        try {
            $numEntries = $zip->numFiles;

            if ($numEntries > $maxEntries) {
                throw new RuntimeException(sprintf('ZIP contains %d entries; max allowed is %d.', $numEntries, $maxEntries));
            }

            for ($i = 0; $i < $numEntries; $i++) {
                $stat = $zip->statIndex($i);
                if (!$stat) {
                    $rejected++;
                    $reasons[] = "entry#{$i}: cannot stat";
                    continue;
                }

                $name = $stat['name'];

                if ($this->isUnsafePath($name)) {
                    $rejected++;
                    $reasons[] = "entry '{$name}': unsafe path (zip-slip).";
                    continue;
                }

                if (str_ends_with($name, '/')) {
                    continue;
                }

                $uncompressed = (int) $stat['size'];
                $compressed = (int) $stat['comp_size'];

                if ($compressed > 0 && $uncompressed / max($compressed, 1) > self::MAX_RATIO) {
                    $rejected++;
                    $reasons[] = "entry '{$name}': suspicious compression ratio (zip bomb?).";
                    continue;
                }

                $uncompressedSeen += $uncompressed;
                if ($uncompressedSeen > $maxUncompressedTotalBytes) {
                    throw new RuntimeException(sprintf(
                        'ZIP uncompressed size exceeds limit (%d bytes).',
                        $maxUncompressedTotalBytes
                    ));
                }

                $reader = function () use ($zip, $name): ?string {
                    $bytes = $zip->getFromName($name);
                    return $bytes === false ? null : $bytes;
                };

                $outcome = $perEntry($name, $uncompressed, $reader);

                if ($outcome === true) {
                    $accepted++;
                } elseif (is_string($outcome)) {
                    $rejected++;
                    $reasons[] = "entry '{$name}': {$outcome}";
                }
            }
        } finally {
            $zip->close();
        }

        return [
            'accepted' => $accepted,
            'rejected' => $rejected,
            'reasons' => $reasons,
        ];
    }

    private function isUnsafePath(string $name): bool
    {
        if ($name === '' || $name[0] === '/' || $name[0] === '\\') {
            return true;
        }

        if (preg_match('#(^|/)\.\.(/|$)#', $name)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $name)) {
            return true;
        }

        return false;
    }
}
