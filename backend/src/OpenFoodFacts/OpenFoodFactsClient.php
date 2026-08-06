<?php

namespace App\OpenFoodFacts;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client over the public Open Food Facts v2 API. Degrades gracefully:
 * any transport/parsing failure results in an empty/null result rather than
 * propagating, so a slow or unreachable OFF never breaks local search.
 */
final class OpenFoodFactsClient
{
    private const BASE_URL = 'https://world.openfoodfacts.org';
    private const FIELDS = 'code,product_name,nutriments,quantity';
    private const TIMEOUT = 3.0;
    private const SEARCH_CACHE_TTL = 60;
    // Open Food Facts asks API consumers to identify themselves; requests
    // with a generic/empty User-Agent are more likely to be rate-limited.
    private const USER_AGENT = 'FoodTracker-MVP/1.0 (+https://github.com/foodtracker)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<array{offId: string, barcode: string, name: string, kcalPer100: float, proteinPer100: float, carbsPer100: float, fatPer100: float, fiberPer100: ?float}>
     */
    public function search(string $query, int $page = 1): array
    {
        $cacheKey = 'off_search_'.md5(strtolower(trim($query))).'_'.$page;

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $page) {
                $item->expiresAfter(self::SEARCH_CACHE_TTL);

                return $this->fetchSearch($query, $page);
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Open Food Facts search failed, degrading to local results only.', [
                'query' => $query,
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function getByBarcode(string $barcode): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL.'/api/v2/product/'.rawurlencode($barcode), [
                'query' => ['fields' => self::FIELDS],
                'timeout' => self::TIMEOUT,
                'headers' => ['User-Agent' => self::USER_AGENT],
            ]);
            $data = $response->toArray();

            if (1 !== ($data['status'] ?? 0) || !isset($data['product'])) {
                return null;
            }

            return $this->mapProduct($data['product']);
        } catch (\Throwable $e) {
            $this->logger->warning('Open Food Facts barcode lookup failed.', [
                'barcode' => $barcode,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<array{offId: string, barcode: string, name: string, kcalPer100: float, proteinPer100: float, carbsPer100: float, fatPer100: float, fiberPer100: ?float}>
     */
    private function fetchSearch(string $query, int $page): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.'/api/v2/search', [
            'query' => [
                'search_terms' => $query,
                'page_size' => 20,
                'page' => $page,
                'fields' => self::FIELDS,
            ],
            'timeout' => self::TIMEOUT,
            'headers' => ['User-Agent' => self::USER_AGENT],
        ]);

        $data = $response->toArray();
        $products = $data['products'] ?? [];

        $mapped = array_map(fn (array $p) => $this->mapProduct($p), $products);

        return array_values(array_filter($mapped));
    }

    private function mapProduct(array $product): ?array
    {
        $code = $product['code'] ?? null;
        $name = trim((string) ($product['product_name'] ?? ''));
        $nutriments = $product['nutriments'] ?? [];
        $kcal = $nutriments['energy-kcal_100g'] ?? null;

        // A food with no calorie data or no name/code is useless to the app.
        if (null === $code || '' === $name || null === $kcal) {
            return null;
        }

        return [
            'offId' => (string) $code,
            'barcode' => (string) $code,
            'name' => $name,
            'kcalPer100' => (float) $kcal,
            'proteinPer100' => (float) ($nutriments['proteins_100g'] ?? 0),
            'carbsPer100' => (float) ($nutriments['carbohydrates_100g'] ?? 0),
            'fatPer100' => (float) ($nutriments['fat_100g'] ?? 0),
            'fiberPer100' => isset($nutriments['fiber_100g']) ? (float) $nutriments['fiber_100g'] : null,
        ];
    }
}
