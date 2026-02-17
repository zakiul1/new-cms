<?php

namespace Plugins\ContactForm\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpCountryResolver
{
    /**
     * Returns: ['code' => 'GB', 'name' => 'United Kingdom'] or null
     */
    public function resolve(?string $ip): ?array
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }

        // Ignore local/private IPs
        if (
            $ip === '127.0.0.1' ||
            $ip === '::1' ||
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            return null;
        }

        return Cache::remember("contactform:geoip:{$ip}", 60 * 60 * 24, function () use ($ip) {
            // ipapi.co example response: { "country_name":"United Kingdom","country":"GB", ... }
            $resp = Http::timeout(4)->get("https://ipapi.co/{$ip}/json/");

            if (!$resp->successful()) {
                return null;
            }

            $json = $resp->json();
            if (!is_array($json)) {
                return null;
            }

            $name = trim((string) ($json['country_name'] ?? ''));
            $code = trim((string) ($json['country'] ?? ''));

            if ($name === '' && $code === '') {
                return null;
            }

            return [
                'name' => $name !== '' ? $name : null,
                'code' => $code !== '' ? strtoupper($code) : null,
            ];
        });
    }
}