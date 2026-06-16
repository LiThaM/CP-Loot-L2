<?php

namespace App\Contexts\Loot\Infrastructure\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\DomCrawler\Crawler;

class Lu4Scraper
{
    private Client $client;

    private string $baseUrl = 'https://wikipedia1.mw2.wiki/lu4/item/';

    private string $hostBase = 'https://wikipedia1.mw2.wiki';

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
            'connect_timeout' => 7,
            'headers' => [
                'User-Agent' => 'L2RecipeTracker/1.0 (personal crafting tool)',
                'Accept' => 'text/html',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
        ]);
    }

    public function fetchItem(int $id): ?array
    {
        $res = $this->fetchItemWithHtml($id);
        if (($res['status'] ?? null) !== 'ok') {
            return null;
        }

        return $res['item'];
    }

    public function fetchItemWithHtml(int $id): array
    {
        try {
            $res = $this->client->get($this->baseUrl.$id, ['http_errors' => false]);
            $code = $res->getStatusCode();
            if ($code === 404) {
                return ['status' => 'missing'];
            }
            if ($code >= 400) {
                return ['status' => 'error', 'code' => $code];
            }
            $html = (string) $res->getBody();
            if (! $html) {
                return ['status' => 'error', 'code' => $code];
            }

            $item = $this->parseItemFromHtml($html, $id);
            if (! $item) {
                return ['status' => 'not_item'];
            }

            return ['status' => 'ok', 'item' => $item, 'html' => $html];
        } catch (RequestException $e) {
            Log::warning('Lu4Scraper: '.$e->getMessage());

            return ['status' => 'error', 'code' => 0];
        } catch (\Exception $e) {
            Log::error('Lu4Scraper: '.$e->getMessage());

            return ['status' => 'error', 'code' => 0];
        }
    }

    private function parseItemFromHtml(string $html, int $id): ?array
    {
        $crawler = new Crawler($html);

        $title = $this->firstText($crawler, '.item_title .item-name__content');
        if (! $title) {
            return null;
        }

        $grade = $this->firstText($crawler, '.item_title .item-grade');
        $rawName = trim(preg_replace('/\s+/', ' ', $title));
        $name = $rawName;
        if ($grade && str_ends_with($rawName, $grade)) {
            $name = trim(substr($rawName, 0, -strlen($grade)));
        }
        if ($name === '' || in_array($name, ['-', '—', '–', '_'], true)) {
            return null;
        }

        $rawType = $this->firstText($crawler, '.item_title .item-name__type') ?? '';
        $category = $this->mapCategoryFromType($rawType);

        $iconSrc = $this->firstAttr($crawler, '.item-icon img', 'src');
        $imageUrl = $this->toAbsoluteUrl($iconSrc);
        if ($imageUrl === $this->hostBase.'/i64/.png') {
            $imageUrl = null;
        }
        $iconName = $this->extractIconName($iconSrc);
        $description = '';
        // The reference wiki lists the price the NPC SELLS the item AT; when a
        // player sells it back to a store they only get ~half. Store that real
        // sell-back value (÷2, floored) so it matches what players actually get.
        $npcSellRaw = $this->extractStatNumber($crawler, 'NPC Sell Price');
        $npcSellPrice = $npcSellRaw !== null ? intdiv((int) $npcSellRaw, 2) : null;

        return [
            'external_id' => $id,
            'name' => $name,
            'icon_name' => $iconName,
            'category' => $category,
            'description' => $description,
            'additional_name' => '',
            'chronicle' => 'LU4',
            'source' => 'lu4',
            'grade' => $grade ?: null,
            'image_url' => $imageUrl,
            'npc_sell_price' => $npcSellPrice,
        ];
    }

    /**
     * Pulls an integer out of a `<div class="stat_line">` whose
     * `<div class="stat_name">` matches the given label. Strips
     * thousands separators (the wiki uses regular spaces) and any
     * trailing currency word like "Adena". Returns null when the
     * stat is absent — most items have NPC Sell Price but a few
     * special / NO-SELL items don't.
     */
    private function extractStatNumber(Crawler $crawler, string $label): ?int
    {
        $value = null;
        $crawler->filter('.stat_line')->each(function (Crawler $node) use ($label, &$value) {
            if ($value !== null) {
                return;
            }
            $name = trim($node->filter('.stat_name')->count() ? $node->filter('.stat_name')->text() : '');
            if ($name !== $label) {
                return;
            }
            $span = $node->filter('span');
            if (! $span->count()) {
                return;
            }
            $raw = trim($span->first()->text());
            // "75 000 Adena" / "1 Adena" / "10 370 500 Adena". Strip
            // any non-digit and parse.
            $digits = preg_replace('/[^\d]/', '', $raw);
            if ($digits !== '' && ctype_digit($digits)) {
                $value = (int) $digits;
            }
        });

        return $value;
    }

    public function downloadIconFromUrl(?string $imageUrl, int $itemId): ?string
    {
        if (! $imageUrl) {
            return null;
        }
        $basename = basename(parse_url($imageUrl, PHP_URL_PATH) ?? '') ?: 'icon.png';
        if (! str_ends_with(strtolower($basename), '.png')) {
            $basename .= '.png';
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename);
        $filename = "items/LU4/{$itemId}-{$safeName}";
        if (Storage::disk('public')->exists($filename)) {
            return "/storage/{$filename}";
        }
        try {
            $res = $this->client->get($imageUrl, ['http_errors' => false]);
            if ($res->getStatusCode() === 200) {
                Storage::disk('public')->put($filename, (string) $res->getBody());

                return "/storage/{$filename}";
            }
        } catch (\Exception $e) {
            Log::warning('Lu4Scraper: image download fail '.$e->getMessage());
        }

        return null;
    }

    private function firstText(Crawler $crawler, string $selector, ?string $attr = null): ?string
    {
        $nodes = $crawler->filter($selector);
        if (! $nodes->count()) {
            return null;
        }
        if ($attr) {
            $val = $nodes->first()->attr($attr);

            return $val !== null ? trim($val) : null;
        }

        return trim($nodes->first()->text());
    }

    private function firstAttr(Crawler $crawler, string $selector, string $attr): ?string
    {
        $nodes = $crawler->filter($selector);
        if (! $nodes->count()) {
            return null;
        }
        $val = $nodes->first()->attr($attr);

        return $val !== null ? trim((string) $val) : null;
    }

    private function toAbsoluteUrl(?string $maybeRelative): ?string
    {
        if (! $maybeRelative) {
            return null;
        }
        if (str_starts_with($maybeRelative, 'http://') || str_starts_with($maybeRelative, 'https://')) {
            return $maybeRelative;
        }
        if (str_starts_with($maybeRelative, '/')) {
            return $this->hostBase.$maybeRelative;
        }

        return $this->hostBase.'/'.$maybeRelative;
    }

    private function extractIconName(?string $src): ?string
    {
        if (! $src) {
            return null;
        }
        if (preg_match('~/i64/(.+?)\\.png~', $src, $m)) {
            return $m[1];
        }

        return null;
    }

    private function mapCategoryFromType(string $rawType): string
    {
        $rawType = trim($rawType);
        if ($rawType === '') {
            return 'Unknown';
        }
        $parts = array_values(array_filter(array_map('trim', explode('/', $rawType))));
        $first = strtolower($parts[0] ?? '');
        if (str_starts_with($first, 'weapon')) {
            return 'Weapon';
        }
        if (str_starts_with($first, 'armor')) {
            return 'Armor';
        }
        if (str_starts_with($first, 'accessory') || str_starts_with($first, 'jewel') || str_starts_with($first, 'jewelry')) {
            return 'Accessory';
        }

        return 'EtcItem';
    }
}
