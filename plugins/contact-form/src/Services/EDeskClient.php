<?php

namespace Plugins\ContactForm\Services;

use Illuminate\Support\Facades\Http;

class EDeskClient
{
    public function send(string $apiUrl, string $apiKey, array $payload): void
    {
        $ip = (string) ($payload['ip'] ?? '');

        $resp = Http::timeout(15)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-Key' => $apiKey,

                // some APIs read IP from headers
                'X-Forwarded-For' => $ip,
                'X-Real-IP' => $ip,
            ])
            ->post($apiUrl, $payload);

        if (!$resp->successful()) {
            throw new \RuntimeException('eDesk API failed: HTTP ' . $resp->status() . ' - ' . $resp->body());
        }
    }
}