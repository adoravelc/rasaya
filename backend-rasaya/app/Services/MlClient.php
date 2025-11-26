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
        $timeout = (int) (config('services.ml_api.timeout') ?? 120);
        $connectTimeout = (int) (config('services.ml_api.connect_timeout') ?? 5);

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if (!empty($key)) {
            // ML service accepts both X-API-KEY and X-API-Key
            $headers['X-API-KEY'] = $key;
        }

        return Http::baseUrl($base)
            ->withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout($connectTimeout);
    }

    public function health()
    {
        return $this->http()->get('/health')->json();
    }

    public function analyze(array $items): array
    {
        try {
            // Build proper JSON body: { request_id, items: [...] }
            $body = [
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'items' => array_values($items),
            ];
            $res = $this->http()->post('/analyze', $body);
            $res->throw();
            return $res->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection failed - ML service is down
            throw new \RuntimeException('Server Machine Learning RASAYA sedang tidak aktif. Mohon menghubungi Admin untuk mengaktifkan service ML.', 503, $e);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // HTTP error (4xx, 5xx)
            $status = $e->response->status();
            if ($status >= 500) {
                throw new \RuntimeException('Server Machine Learning RASAYA mengalami gangguan internal. Mohon menghubungi Admin.', $status, $e);
            }
            // Re-throw other errors (4xx client errors)
            throw $e;
        }
    }

    /**
     * Send feedback to ML to adjust keyword→category weights.
     * @param array $keywords list of strings
     * @param string|null $from optional previous category name
     * @param string|null $to optional corrected category name
     * @param float $delta adjustment magnitude (default 0.2)
     */
    public function feedback(array $keywords, ?string $from = null, ?string $to = null, float $delta = 0.2): void
    {
        if (empty($keywords) || (!$from && !$to)) {
            return; // nothing to do
        }
        $payload = [
            'keywords' => array_values(array_filter(array_map(fn($x)=> (string)$x, $keywords))),
            'from_category' => $from,
            'to_category' => $to,
            'delta' => $delta,
        ];
        $res = $this->http()->post('/feedback', $payload);
        // don't throw hard; ignore failures silently to avoid UX impact
        // ignore failures; optional logging could be added via event/queue if needed
    }
}

