<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class MlClient
{
    protected function http(): PendingRequest
    {
        $base = config('services.ml_api.url');
        $key  = config('services.ml_api.key');

        return Http::baseUrl($base)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                // 'X-API-Key' => $key, // aktifkan jika Colab memeriksa API key
            ])
            ->timeout(30);
    }

    public function health()
    {
        return $this->http()->get('/health')->json();
    }

    public function analyze(array $items): array
    {
        return $this->http()->post('/analyze', [
            'items'   => $items,
            'options' => ['cluster_on' => 'negative_only', 'k' => 5],
        ])->json();
    }
}
