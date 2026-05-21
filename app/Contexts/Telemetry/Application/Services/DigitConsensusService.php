<?php

namespace App\Contexts\Telemetry\Application\Services;

use App\Contexts\Telemetry\Domain\Models\DigitTemplateSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Computes the crowdsourced digit-template consensus.
 *
 * Pipeline:
 *  1. Group submissions by char.
 *  2. Cluster within each char via pHash Hamming distance (≤ HAMMING_THRESHOLD).
 *  3. Rank clusters by distinct anon_token count (vote = unique contributors).
 *  4. Keep top-N clusters per char (TOP_CLUSTERS).
 *  5. From each winning cluster pick the representative template by max
 *     sharpness, falling back to oldest id for determinism.
 *  6. Pack the survivors into a flat zip as <char>_<cluster_rank>.png.
 *
 * Public consumers should call buildZip() (cached via storage) — never the
 * private helpers directly.
 */
class DigitConsensusService
{
    public const HAMMING_THRESHOLD = 4;
    public const TOP_CLUSTERS = 10;
    public const MIN_CONTRIBUTORS_PER_CHAR = 3;
    public const CACHE_KEY_PREFIX = 'consensus/digits';

    public const ALLOWED_CHARS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'slash'];

    /**
     * Compute aHash (8x8 average-hash, 64 bits → 16 hex chars).
     * Returns null when GD cannot decode the bytes.
     */
    public function perceptualHash(string $pngBytes): ?string
    {
        $img = @imagecreatefromstring($pngBytes);
        if ($img === false) {
            return null;
        }

        $small = imagecreatetruecolor(8, 8);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 8, 8, imagesx($img), imagesy($img));
        imagefilter($small, IMG_FILTER_GRAYSCALE);

        $pixels = [];
        $sum = 0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $l = $rgb & 0xFF;
                $pixels[] = $l;
                $sum += $l;
            }
        }
        imagedestroy($img);
        imagedestroy($small);

        $mean = $sum / 64;
        $bits = '';
        foreach ($pixels as $p) {
            $bits .= $p >= $mean ? '1' : '0';
        }

        $hex = '';
        for ($i = 0; $i < 64; $i += 4) {
            $hex .= dechex(bindec(substr($bits, $i, 4)));
        }
        return $hex;
    }

    /**
     * Laplacian variance as a sharpness proxy. Higher = sharper.
     * Returns null when GD cannot decode.
     */
    public function sharpness(string $pngBytes): ?float
    {
        $img = @imagecreatefromstring($pngBytes);
        if ($img === false) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        imagefilter($img, IMG_FILTER_GRAYSCALE);

        // Manual Laplacian (8-connected) — imageconvolution clamps in unwanted
        // ways. Compute response per pixel against the 4-neighbour kernel.
        $values = [];
        $sum = 0.0;
        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                $c = imagecolorat($img, $x, $y) & 0xFF;
                $u = imagecolorat($img, $x, $y - 1) & 0xFF;
                $d = imagecolorat($img, $x, $y + 1) & 0xFF;
                $l = imagecolorat($img, $x - 1, $y) & 0xFF;
                $r = imagecolorat($img, $x + 1, $y) & 0xFF;
                $lap = 4 * $c - ($u + $d + $l + $r);
                $values[] = $lap;
                $sum += $lap;
            }
        }
        imagedestroy($img);

        $n = count($values);
        if ($n === 0) {
            return null;
        }
        $mean = $sum / $n;
        $variance = 0.0;
        foreach ($values as $v) {
            $variance += ($v - $mean) * ($v - $mean);
        }
        return $variance / $n;
    }

    /**
     * Hamming distance between two equal-length hex strings.
     */
    public function hamming(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b)) {
            return PHP_INT_MAX;
        }
        $distance = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $distance += substr_count(
                str_pad(decbin(hexdec($a[$i]) ^ hexdec($b[$i])), 4, '0', STR_PAD_LEFT),
                '1'
            );
        }
        return $distance;
    }

    /**
     * Group submissions (already filtered for one char) into clusters by pHash
     * Hamming-distance proximity. Returns an array of clusters (each cluster
     * is an array of DigitTemplateSubmission models).
     */
    public function cluster(Collection $submissions): array
    {
        // Drop submissions without a phash — they can't participate.
        $items = $submissions->filter(fn ($s) => filled($s->phash))->values();
        $n = $items->count();
        if ($n === 0) {
            return [];
        }

        // Union-find over the indices.
        $parent = range(0, $n - 1);
        $find = function (int $x) use (&$parent, &$find): int {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        $union = function (int $a, int $b) use (&$parent, $find) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$ra] = $rb;
            }
        };

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($this->hamming($items[$i]->phash, $items[$j]->phash) <= self::HAMMING_THRESHOLD) {
                    $union($i, $j);
                }
            }
        }

        $clusters = [];
        for ($i = 0; $i < $n; $i++) {
            $root = $find($i);
            $clusters[$root][] = $items[$i];
        }
        return array_values($clusters);
    }

    /**
     * Pick the representative template from a cluster: highest sharpness, then
     * lowest id as deterministic tie-break.
     */
    public function representative(array $cluster): DigitTemplateSubmission
    {
        usort($cluster, function ($a, $b) {
            $sa = $a->sharpness ?? -1;
            $sb = $b->sharpness ?? -1;
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            return $a->id <=> $b->id;
        });
        return $cluster[0];
    }

    public function countContributors(array $cluster): int
    {
        $seen = [];
        foreach ($cluster as $sub) {
            $seen[$sub->anon_token_id] = true;
        }
        return count($seen);
    }

    /**
     * Build the consensus zip and persist it on the client_blobs disk.
     * Returns [zipPath, sha256, stats] or null when the pool is too small
     * (zero chars cleared the contributor threshold).
     */
    public function buildAndCache(?string $botVersion = null): ?array
    {
        $query = DigitTemplateSubmission::query()->whereNotNull('phash');
        if ($botVersion !== null) {
            $query->where('bot_version', $botVersion);
        }
        $all = $query->get();

        $byChar = $all->groupBy('char');
        $entries = [];
        $statsByChar = [];

        foreach (self::ALLOWED_CHARS as $char) {
            $subs = $byChar->get($char, collect());
            if ($subs->isEmpty()) {
                $statsByChar[$char] = ['clusters' => 0, 'contributors' => 0];
                continue;
            }
            $clusters = $this->cluster($subs);

            // Rank clusters by distinct contributors.
            usort($clusters, function ($a, $b) {
                return $this->countContributors($b) <=> $this->countContributors($a);
            });

            // Apply the contributor floor to the WINNING cluster — if even the
            // best cluster has <3 contributors we don't emit anything for this
            // char (avoid pushing noisy single-contributor templates).
            if ($this->countContributors($clusters[0]) < self::MIN_CONTRIBUTORS_PER_CHAR) {
                $statsByChar[$char] = ['clusters' => count($clusters), 'contributors' => 0];
                continue;
            }

            $top = array_slice($clusters, 0, self::TOP_CLUSTERS);
            $statsByChar[$char] = [
                'clusters' => count($top),
                'contributors' => $this->countContributors($top[0]),
            ];

            foreach ($top as $rank => $cluster) {
                $entries[] = [
                    'char' => $char,
                    'rank' => $rank,
                    'submission' => $this->representative($cluster),
                ];
            }
        }

        if (empty($entries)) {
            return null;
        }

        $disk = Storage::disk('client_blobs');
        $tempZip = tempnam(sys_get_temp_dir(), 'consensus_').'.zip';
        $zip = new ZipArchive();
        if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempZip);
            return null;
        }

        foreach ($entries as $entry) {
            $sub = $entry['submission'];
            if (!$disk->exists($sub->storage_path)) {
                continue;
            }
            $zip->addFromString(
                sprintf('%s_%d.png', $entry['char'], $entry['rank']),
                $disk->get($sub->storage_path)
            );
        }
        $zip->close();

        $zipBytes = file_get_contents($tempZip);
        @unlink($tempZip);
        if ($zipBytes === false || $zipBytes === '') {
            return null;
        }

        $sha256 = hash('sha256', $zipBytes);
        $relPath = sprintf(
            '%s/%s.zip',
            self::CACHE_KEY_PREFIX,
            $botVersion ? 'v_'.preg_replace('/[^\w.\-+]/', '_', $botVersion) : 'all'
        );
        $disk->put($relPath, $zipBytes);

        return [
            'zip_path' => $relPath,
            'sha256' => $sha256,
            'size_bytes' => strlen($zipBytes),
            'entries' => count($entries),
            'stats_by_char' => $statsByChar,
            'rebuilt_at' => now()->toIso8601String(),
        ];
    }

    public function cachedZipPath(?string $botVersion = null): string
    {
        return sprintf(
            '%s/%s.zip',
            self::CACHE_KEY_PREFIX,
            $botVersion ? 'v_'.preg_replace('/[^\w.\-+]/', '_', $botVersion) : 'all'
        );
    }
}
