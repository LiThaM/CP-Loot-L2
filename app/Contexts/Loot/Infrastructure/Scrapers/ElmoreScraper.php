<?php

namespace App\Contexts\Loot\Infrastructure\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ElmoreScraper
{
    private Client $client;

    private string $apiBase = 'https://resources-service.elmorelab.com/Resources/getItemInfo';

    private string $imageBase = 'https://resources.elmorelab.com/images/';

    /**
     * Mapping of ElmoreLab 'kind' values to L2 item categories.
     * kind=2 => Weapon/Armor/Accessory (equipment)
     * kind=3 => EtcItem (materials, misc, etc.)
     * kind=1 => Armor
     */
    private array $kindMap = [
        1 => 'Armor',
        2 => 'Weapon',
        3 => 'EtcItem',
    ];

    /**
     * Available chronicles on ElmoreLab.
     */
    public static array $chronicles = ["c1", "c2", "c3", 'c4', 'c5', 'IL', "hb"];

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
            'headers' => [
                'User-Agent' => 'L2-CP-Loot-Manager/1.0',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Fetch a single item from ElmoreLab by ID and chronicle.
     *
     * @return array|null Parsed item data or null if item doesn't exist.
     */
    public function fetchItem(int $itemId, string $chronicle = 'IL'): ?array
    {
        try {
            $response = $this->client->get($this->apiBase, [
                'query' => [
                    'alias' => $chronicle,
                    'itemId' => $itemId,
                ],
            ]);

            // 204 = item not found
            if ($response->getStatusCode() === 204) {
                return null;
            }

            $body = $response->getBody()->getContents();
            if (empty($body)) {
                return null;
            }

            $data = json_decode($body, true);
            if (! $data || empty($data['itemName'])) {
                return null;
            }

            return [
                'external_id' => $data['itemClassId'],
                'name' => $data['itemName'],
                'icon_name' => $data['value'] ?? null,
                'category' => $this->kindMap[$data['kind'] ?? 0] ?? 'Unknown',
                'description' => $data['description'] ?? '',
                'additional_name' => $data['additionalName'] ?? '',
                'chronicle' => $chronicle,
                'source' => 'elmore',
                'image_url' => $data['value'] ? $this->imageBase.$data['value'].'.jpg' : null,
            ];
        } catch (RequestException $e) {
            // 204 responses come as exceptions in some Guzzle configs
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 204) {
                return null;
            }
            Log::warning("ElmoreScraper: Error fetching item {$itemId} for {$chronicle}: ".$e->getMessage());

            return null;
        } catch (\Exception $e) {
            Log::error("ElmoreScraper: Unexpected error for item {$itemId}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Download item icon to local storage.
     *
     * @return string|null Local path relative to storage/app/public, or null on failure.
     */
    public function downloadIcon(string $iconName, int $itemId, string $chronicle = 'IL'): ?string
    {
        $safeChronicle = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $chronicle) ?: 'IL';
        $safeIconName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $iconName);
        $filename = "items/{$safeChronicle}/{$itemId}-{$safeIconName}.jpg";

        // Skip if already downloaded
        if (Storage::disk('public')->exists($filename)) {
            return "/storage/{$filename}";
        }

        try {
            $response = $this->client->get($this->imageBase.$iconName.'.jpg');

            if ($response->getStatusCode() === 200) {
                Storage::disk('public')->put($filename, $response->getBody()->getContents());

                return "/storage/{$filename}";
            }
        } catch (\Exception $e) {
            Log::warning("ElmoreScraper: Could not download icon {$iconName}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Determine grade from item name heuristics (ElmoreLab API doesn't provide grade directly).
     * This is a best-effort mapping based on L2 naming conventions.
     */
    public function guessGrade(string $itemName, int $itemId): string
    {
        // Some well-known S-grade weapon/armor IDs (Interlude)
        $sGradePatterns = [
            'Draconic', 'Imperial', 'Arcana', 'Dynasty', 'Vesper', 'Vorpal',
            'Elegia', 'Moirai', 'Icarus', 'Heavens Divider', 'Angel Slayer',
            'Soul Separator', 'Basalt Battlehammer', 'Saint Spear', 'Shyeed',
            'Tateossian', 'Major Arcana',
        ];

        $aGradePatterns = [
            'Tallum', 'Majestic', 'Dark Crystal', 'Nightmare', 'Blue Wolf',
            'Doom', 'Sword of Ipos', 'Carnage', 'Inferno',
        ];

        $bGradePatterns = [
            'Zubei', 'Avadon', 'Doom Plate', 'Blue Wolf',
        ];

        foreach ($sGradePatterns as $pattern) {
            if (stripos($itemName, $pattern) !== false) {
                return 'S';
            }
        }
        foreach ($aGradePatterns as $pattern) {
            if (stripos($itemName, $pattern) !== false) {
                return 'A';
            }
        }
        foreach ($bGradePatterns as $pattern) {
            if (stripos($itemName, $pattern) !== false) {
                return 'B';
            }
        }

        return 'NG'; // default to No Grade
    }
}
