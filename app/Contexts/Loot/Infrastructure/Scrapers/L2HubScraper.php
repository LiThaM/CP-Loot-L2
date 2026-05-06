<?php

namespace App\Contexts\Loot\Infrastructure\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class L2HubScraper
{
    private Client $client;

    private string $baseUrl = 'https://l2hub.info';

    private string $sitemapUrl = 'https://l2hub.info/sitemap.txt';

    private string $iconBase = 'https://l2hub.info/s/icons-gf/';

    /**
     * Chronicle URL prefixes on l2hub.info.
     * GF has no prefix (root), rest use /{code}/.
     */
    public static array $chronicles = [
        'C1' => '/c1',
        'C2' => '/c2',
        'C3' => '/c3',
        'C4' => '/c4',
        'C5' => '/c5',
        'IL' => '/il',
        'CT1' => '/ct1',
        'GF' => '',
    ];

    /**
     * Crystal type to grade mapping from l2hub data.
     */
    private array $crystalGradeMap = [
        'none' => 'NG',
        'd' => 'D',
        'c' => 'C',
        'b' => 'B',
        'a' => 'A',
        's' => 'S',
        's80' => 'S',
        's84' => 'S',
    ];

    /**
     * Item type to category mapping from l2hub data.
     */
    private array $typeCategoryMap = [
        'weapon' => 'Weapon',
        'armor' => 'Armor',
        'accessory' => 'Accessory',
        'etcitem' => 'EtcItem',
    ];

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 20,
            'connect_timeout' => 10,
            'headers' => [
                'User-Agent' => 'AdenaLedger/1.0 (personal crafting tool)',
                'Accept' => 'text/html',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);
    }

    /**
     * Fetch all recipe slugs from the GF sitemap.
     * Returns array of slugs like ['rp_soul_bow', 'rp_soul_bow_i', ...].
     */
    public function fetchRecipeSlugs(): array
    {
        try {
            $response = $this->client->get($this->sitemapUrl);
            $text = (string) $response->getBody();
            $lines = explode("\n", $text);

            $slugs = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('#/items/(rp_.+)$#', $line, $m)) {
                    $slugs[] = $m[1];
                }
            }

            return array_unique($slugs);
        } catch (\Exception $e) {
            Log::error('L2HubScraper: Failed to fetch sitemap: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Fetch a recipe page and extract structured data.
     *
     * @param  string  $slug  Recipe slug (e.g. 'rp_soul_bow')
     * @param  string  $chronicle  Chronicle code (e.g. 'IL', 'C4')
     * @return array{status: string, recipe?: array}
     */
    public function fetchRecipe(string $slug, string $chronicle = 'IL'): array
    {
        $prefix = self::$chronicles[$chronicle] ?? self::$chronicles['IL'];
        $url = $this->baseUrl . $prefix . '/items/' . $slug;

        try {
            $response = $this->client->get($url, ['http_errors' => false]);
            $code = $response->getStatusCode();

            if ($code === 404) {
                return ['status' => 'missing'];
            }
            if ($code >= 400) {
                return ['status' => 'error', 'code' => $code];
            }

            $html = (string) $response->getBody();
            if (! $html) {
                return ['status' => 'error', 'code' => $code];
            }

            $data = $this->extractJsonData($html);
            if (! $data) {
                return ['status' => 'error', 'message' => 'No JSON data found'];
            }

            $recipe = $this->parseRecipeFromJson($data, $slug, $chronicle, $url);
            if (! $recipe) {
                return ['status' => 'not_recipe'];
            }

            return ['status' => 'recipe', 'recipe' => $recipe];

        } catch (RequestException $e) {
            Log::warning("L2HubScraper: Error fetching {$slug} for {$chronicle}: " . $e->getMessage());

            return ['status' => 'error', 'code' => 0];
        } catch (\Exception $e) {
            Log::error("L2HubScraper: Unexpected error for {$slug}: " . $e->getMessage());

            return ['status' => 'error', 'code' => 0];
        }
    }

    /**
     * Extract the JSON data embedded in the page's __staticRouterHydrationData.
     */
    private function extractJsonData(string $html): ?array
    {
        if (! preg_match('/window\.__staticRouterHydrationData\s*=\s*JSON\.parse\("(.+?)"\);/s', $html, $m)) {
            return null;
        }

        $jsonStr = stripcslashes($m[1]);
        $data = json_decode($jsonStr, true);

        if (! $data || ! isset($data['loaderData'])) {
            return null;
        }

        $loader = array_values($data['loaderData'])[0] ?? null;

        return $loader['data']['item'] ?? $loader['item'] ?? null;
    }

    /**
     * Parse recipe data from the extracted JSON item structure.
     */
    private function parseRecipeFromJson(array $item, string $slug, string $chronicle, string $url): ?array
    {
        if (empty($item['recipe'])) {
            return null;
        }

        $recipeData = $item['recipe'][0];
        $itemName = is_array($item['name']) ? ($item['name']['en'] ?? '') : ($item['name'] ?? '');

        if (! $itemName) {
            return null;
        }

        // Build materials (excluding the recipe itself from the list)
        $materials = [];
        foreach ($recipeData['materialList'] ?? [] as $mat) {
            $matId = (int) ($mat['item']['id'] ?? 0);
            if ($matId <= 0 || $matId === (int) $item['id']) {
                continue;
            }
            // Also skip adena (id 57)
            if ($matId === 57) {
                continue;
            }

            $matName = is_array($mat['item']['name'] ?? null)
                ? ($mat['item']['name']['en'] ?? '')
                : ($mat['item']['name'] ?? '');

            $materials[] = [
                'external_id' => $matId,
                'name' => $matName,
                'quantity' => (int) ($mat['count'] ?? 1),
                'icon_name' => $mat['item']['icon'] ?? null,
                'item_name' => $mat['item']['itemName'] ?? null,
            ];
        }

        // Build outputs
        $outputs = [];
        foreach ($recipeData['productList'] ?? [] as $prod) {
            $prodId = (int) ($prod['item']['id'] ?? 0);
            if ($prodId <= 0) {
                continue;
            }

            $prodName = is_array($prod['item']['name'] ?? null)
                ? ($prod['item']['name']['en'] ?? '')
                : ($prod['item']['name'] ?? '');

            $outputs[] = [
                'external_id' => $prodId,
                'name' => $prodName,
                'quantity' => (int) ($prod['count'] ?? 1),
                'chance' => null,
                'icon_name' => $prod['item']['icon'] ?? null,
                'item_name' => $prod['item']['itemName'] ?? null,
            ];
        }

        $outputExternalId = $outputs[0]['external_id'] ?? null;
        $outputName = $outputs[0]['name'] ?? null;
        $outputIconName = $outputs[0]['icon_name'] ?? null;

        $grade = $this->crystalGradeMap[$item['crystalType'] ?? 'none'] ?? 'NG';
        $category = $this->typeCategoryMap[$item['type'] ?? 'etcitem'] ?? 'EtcItem';

        return [
            'external_id' => (int) $item['id'],
            'chronicle' => $chronicle,
            'slug' => $slug,
            'name' => $itemName,
            'grade' => $grade,
            'category' => $category,
            'success_rate' => (float) ($recipeData['successRate'] ?? 0),
            'mp_cost' => (int) ($recipeData['mpConsume'] ?? 0),
            'adena_fee' => 0,
            'output_external_id' => $outputExternalId,
            'output_name' => $outputName,
            'output_icon_name' => $outputIconName,
            'outputs' => $outputs,
            'materials' => $materials,
            'icon_name' => $item['icon'] ?? null,
            'image_url' => $item['icon'] ? $this->iconBase . $item['icon'] . '.png' : null,
            'scraper_url' => $url,
        ];
    }

    /**
     * Download an item icon from l2hub and store locally.
     *
     * @return string|null Local storage path or null on failure.
     */
    public function downloadIcon(string $iconName, int $itemId, string $chronicle = 'IL'): ?string
    {
        $safeChronicle = preg_replace('/[^A-Za-z0-9_-]/', '', $chronicle) ?: 'IL';
        $safeIcon = preg_replace('/[^A-Za-z0-9._-]/', '_', $iconName);
        $filename = "items/{$safeChronicle}/{$itemId}-{$safeIcon}.png";

        if (Storage::disk('public')->exists($filename)) {
            return "/storage/{$filename}";
        }

        try {
            $response = $this->client->get($this->iconBase . $iconName . '.png');

            if ($response->getStatusCode() === 200) {
                Storage::disk('public')->put($filename, $response->getBody()->getContents());

                return "/storage/{$filename}";
            }
        } catch (\Exception $e) {
            Log::warning("L2HubScraper: Could not download icon {$iconName}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch an individual item page for enrichment data.
     *
     * @return array|null Parsed item data or null if not found.
     */
    public function fetchItem(string $slug, string $chronicle = 'IL'): ?array
    {
        $prefix = self::$chronicles[$chronicle] ?? self::$chronicles['IL'];
        $url = $this->baseUrl . $prefix . '/items/' . $slug;

        try {
            $response = $this->client->get($url, ['http_errors' => false]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $html = (string) $response->getBody();
            $item = $this->extractJsonData($html);
            if (! $item) {
                return null;
            }

            $name = is_array($item['name']) ? ($item['name']['en'] ?? '') : ($item['name'] ?? '');
            $grade = $this->crystalGradeMap[$item['crystalType'] ?? 'none'] ?? 'NG';

            $type = $item['type'] ?? 'etcitem';
            $slotBitType = $item['slotBitType'] ?? 'none';
            $category = $this->resolveCategory($type, $slotBitType);

            return [
                'external_id' => (int) $item['id'],
                'name' => $name,
                'grade' => $grade,
                'category' => $category,
                'icon_name' => $item['icon'] ?? null,
                'image_url' => ($item['icon'] ?? null) ? $this->iconBase . $item['icon'] . '.png' : null,
                'description' => $item['desc'] ?? null,
                'chronicle' => $chronicle,
                'source' => 'l2hub',
            ];
        } catch (\Exception $e) {
            Log::warning("L2HubScraper: Error fetching item {$slug} for {$chronicle}: " . $e->getMessage());

            return null;
        }
    }

    /**
     * Resolve item category from l2hub type and slot fields.
     */
    private function resolveCategory(string $type, string $slotBitType): string
    {
        if ($type === 'weapon') {
            return 'Weapon';
        }
        if ($type === 'armor') {
            if (in_array($slotBitType, ['rear', 'lear', 'rfinger', 'lfinger', 'neck'], true)) {
                return 'Accessory';
            }

            return 'Armor';
        }

        return 'EtcItem';
    }
}
