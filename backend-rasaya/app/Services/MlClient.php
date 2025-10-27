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

    public function analyze(array $payload): array
    {
        $payload['request_id'] = $payload['request_id'] ?? (string) \Illuminate\Support\Str::uuid();
        $res = $this->http()->post('/analyze', ['items' => $payload]);
        $res->throw();
        return $res->json();
    }
}

