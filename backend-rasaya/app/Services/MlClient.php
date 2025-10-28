<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

class MlClient
{
    protected function http(): PendingRequest
    {
        $base = config('services.ml_api.url');
        $key = config('services.ml_api.key');

        return Http::baseUrl($base)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                // 'X-API-Key' => $key,
            ])
            ->timeout(60);
    }

    public function health()
    {
        return $this->http()->get('/health')->json();
    }

    public function analyze(array $items): array
    {
        // Build proper JSON body: { request_id, items: [...] }
        $body = [
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'items' => array_values($items),
        ];
        $res = $this->http()->post('/analyze', $body);
        $res->throw();
        return $res->json();
    }
}

