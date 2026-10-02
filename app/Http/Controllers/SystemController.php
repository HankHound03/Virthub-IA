<?php

namespace App\Http\Controllers;

use App\Support\FeedReader;
use App\Support\SystemStatusMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoints JSON del panel: estado del sistema y titulares RSS.
 */
class SystemController extends Controller
{
    public function __construct(
        private readonly SystemStatusMonitor $monitor,
        private readonly FeedReader $feeds,
    ) {}

    public function status(): JsonResponse
    {
        $ttl = max(1, (int) config('virthub.status.cache_seconds', 15));

        return response()->json([
            'status' => Cache::remember('virthub.system_status', $ttl, fn (): array => $this->monitor->snapshot()),
        ]);
    }

    public function linuxNews(): JsonResponse
    {
        return response()->json([
            'items' => $this->feeds->items('virthub.news.linux', (string) config('virthub.feeds.linux')),
        ]);
    }

    public function cyberNews(): JsonResponse
    {
        return response()->json([
            'items' => $this->feeds->items('virthub.news.cyber', (string) config('virthub.feeds.cyber')),
        ]);
    }
}
