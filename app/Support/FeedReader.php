<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lee y cachea los feeds RSS del panel de inicio.
 */
class FeedReader
{
    /**
     * @return array<int, array{title: string, link: string}>
     */
    public function items(string $cacheKey, string $feedUrl): array
    {
        $ttl = max(1, (int) config('virthub.feeds.cache_seconds', 300));

        return Cache::remember($cacheKey, $ttl, fn (): array => $this->fetch($feedUrl));
    }

    /**
     * @return array<int, array{title: string, link: string}>
     */
    private function fetch(string $feedUrl): array
    {
        try {
            $response = Http::timeout(max(1, (int) config('virthub.feeds.timeout_seconds', 8)))
                ->get($feedUrl);

            if (! $response->successful()) {
                return [];
            }

            $xml = @simplexml_load_string($response->body());

            if (! $xml || ! isset($xml->channel->item)) {
                return [];
            }

            $limit = max(1, (int) config('virthub.feeds.items', 6));
            $items = [];

            foreach ($xml->channel->item as $item) {
                $items[] = [
                    'title' => (string) $item->title,
                    'link' => (string) $item->link,
                ];

                if (count($items) >= $limit) {
                    break;
                }
            }

            return $items;
        } catch (Throwable) {
            return [];
        }
    }
}
