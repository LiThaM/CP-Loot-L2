<?php

namespace App\Contexts\Loot\Infrastructure\Scrapers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class Lu4RecipeScraper
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

    public function fetchRecipe(int $id): array
    {
        $probe = $this->probePage($id);
        if ($probe['status'] !== 'ok') {
            return $probe;
        }

        $recipe = $this->parseRecipeFromHtml($probe['html'], $id);
        if (! $recipe) {
            return ['status' => 'not_recipe'];
        }

        return ['status' => 'recipe', 'recipe' => $recipe];
    }

    private function probePage(int $id): array
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

            return ['status' => 'ok', 'html' => $html];
        } catch (RequestException $e) {
            Log::warning('Lu4RecipeScraper: '.$e->getMessage());

            return ['status' => 'error', 'code' => 0];
        } catch (\Exception $e) {
            Log::error('Lu4RecipeScraper: '.$e->getMessage());

            return ['status' => 'error', 'code' => 0];
        }
    }

    public function parseRecipeFromHtml(string $html, int $id): ?array
    {
        $crawler = new Crawler($html);
        if (! $crawler->filter('.recipe_result')->count()) {
            return null;
        }

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
        if ($name === '') {
            return null;
        }

        $iconSrc = $this->firstAttr($crawler, '.item-icon img', 'src');
        $imageUrl = $this->toAbsoluteUrl($iconSrc);
        $iconName = $this->extractIconName($iconSrc);

        $successRate = 0.0;
        $mpCost = 0;
        $adenaFee = 0;
        $outputExternalId = null;
        $outputName = null;
        $outputIconName = null;
        $outputImageUrl = null;
        $outputs = [];

        foreach ($crawler->filter('.recipe_result .stat_line') as $line) {
            $lineCrawler = new Crawler($line);
            $label = trim((string) $this->firstText($lineCrawler, '.stat_name'));
            $value = trim((string) $this->firstText($lineCrawler, '.stat_value'));
            if ($label === 'Success Rate') {
                $successRate = (float) (preg_replace('/[^0-9.]/', '', $value) ?: 0);
            } elseif ($label === 'MP Consume') {
                $mpCost = (int) (preg_replace('/\D/', '', $value) ?: 0);
            } elseif (str_contains($label, 'Adena') || str_contains($label, 'Fee')) {
                $adenaFee = (int) (preg_replace('/\D/', '', $value) ?: 0);
            } elseif ($label === 'Result') {
                foreach ($lineCrawler->filter('.stat_describe a.item-name') as $a) {
                    $linkCrawler = new Crawler($a);
                    $href = $linkCrawler->attr('href') ?: '';
                    $parsedId = $this->parseExternalIdFromHref($href);
                    if ($parsedId === null) {
                        continue;
                    }

                    $n1 = $this->firstText($linkCrawler, '.item-name__class-1');
                    $n2 = $this->firstText($linkCrawler, '.item-name__class-2');
                    $outName = trim(implode(' ', array_filter([$n1, $n2], fn ($v) => $v !== null && $v !== '')));
                    if ($outName === '') {
                        $outName = trim((string) $linkCrawler->text());
                    }

                    $outIconSrc = $this->firstAttr($linkCrawler, 'img', 'src');
                    $outputs[] = [
                        'external_id' => $parsedId,
                        'name' => $outName,
                        'quantity' => 1,
                        'chance' => null,
                        'icon_name' => $this->extractIconName($outIconSrc),
                        'image_url' => $this->toAbsoluteUrl($outIconSrc),
                    ];
                }

                if (count($outputs) > 0) {
                    $outputExternalId = $outputs[0]['external_id'];
                    $outputName = $outputs[0]['name'];
                    $outputIconName = $outputs[0]['icon_name'];
                    $outputImageUrl = $outputs[0]['image_url'];
                }
            }
        }

        $materials = [];
        foreach ($crawler->filter('.accordion-button') as $btn) {
            if (! ($btn instanceof \DOMElement)) {
                continue;
            }
            if ($this->hasAncestorWithClass($btn, 'accordion-body')) {
                continue;
            }

            $btnCrawler = new Crawler($btn);
            $link = $btnCrawler->filter('a.item-name')->first();
            if (! $link->count()) {
                continue;
            }
            $href = $link->attr('href') ?: '';
            $matExternalId = $this->parseExternalIdFromHref($href);
            if ($matExternalId === null) {
                continue;
            }
            if ($matExternalId === 57 || $matExternalId === $id) {
                continue;
            }

            $qty = 1;
            $amountNode = $btnCrawler->filter('.material-amount')->first();
            if ($amountNode->count()) {
                $attr = $amountNode->attr('data-initial-amount');
                $raw = $attr !== null && $attr !== '' ? $attr : $amountNode->text();
                $parsedQty = (int) (preg_replace('/\D/', '', $raw) ?: 0);
                $qty = $parsedQty > 0 ? $parsedQty : 1;
            }

            $n1 = $this->firstText($link, '.item-name__class-1');
            $n2 = $this->firstText($link, '.item-name__class-2');
            $matName = trim(implode(' ', array_filter([$n1, $n2], fn ($v) => $v !== null && $v !== '')));
            if ($matName === '') {
                $matName = trim((string) $link->text());
            }

            $matIconSrc = $this->firstAttr($link, 'img', 'src');
            $materials[] = [
                'external_id' => $matExternalId,
                'name' => $matName,
                'quantity' => $qty,
                'icon_name' => $this->extractIconName($matIconSrc),
                'image_url' => $this->toAbsoluteUrl($matIconSrc),
            ];
        }

        return [
            'external_id' => $id,
            'chronicle' => 'LU4',
            'name' => $name,
            'success_rate' => $successRate,
            'mp_cost' => $mpCost,
            'adena_fee' => $adenaFee,
            'output_external_id' => $outputExternalId,
            'output_name' => $outputName,
            'output_icon_name' => $outputIconName,
            'output_image_url' => $outputImageUrl,
            'outputs' => $outputs,
            'materials' => $materials,
            'icon_name' => $iconName,
            'image_url' => $imageUrl,
            'scraper_url' => $this->baseUrl.$id,
        ];
    }

    private function firstText(Crawler $crawler, string $selector): ?string
    {
        if ($crawler instanceof Crawler) {
            $nodes = $crawler->filter($selector);
            if ($nodes->count()) {
                return trim($nodes->first()->text());
            }
        }

        return null;
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

    private function parseExternalIdFromHref(string $href): ?int
    {
        if (preg_match('~/item/(\\d+)~', $href, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function hasAncestorWithClass(\DOMElement $node, string $className): bool
    {
        $current = $node->parentNode;
        while ($current instanceof \DOMElement) {
            $classes = $current->getAttribute('class') ?: '';
            if ($classes !== '') {
                $classList = preg_split('/\\s+/', trim($classes)) ?: [];
                if (in_array($className, $classList, true)) {
                    return true;
                }
            }
            $current = $current->parentNode;
        }

        return false;
    }
}
