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

        // ✅ Validate IP format first
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        // ✅ Ignore local/private/reserved IPs
        // (Real public IP should be passed from controller using proxy headers)
        if (
            $ip === '127.0.0.1' ||
            $ip === '::1' ||
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            return null;
        }

        // ✅ Cache (24h)
        return Cache::remember("contactform:geoip:{$ip}", 60 * 60 * 24, function () use ($ip) {
            // Prefer ipapi.co; if it fails, fallback to ipwho.is
            $result = $this->fromIpApiCo($ip);

            if ($result !== null) {
                return $result;
            }

            return $this->fromIpWhoIs($ip);
        });
    }

    private function fromIpApiCo(string $ip): ?array
    {
        try {
            // ipapi.co: { "country_name":"United Kingdom","country":"GB", ... }
            $resp = Http::timeout(6)->retry(1, 150)->get("https://ipapi.co/{$ip}/json/");

            if (!$resp->successful()) {
                return null;
            }

            $json = $resp->json();
            if (!is_array($json)) {
                return null;
            }

            // ipapi.co returns "error": true sometimes
            if (!empty($json['error'])) {
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
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fromIpWhoIs(string $ip): ?array
    {
        try {
            // ipwho.is: { "success": true, "country":"United Kingdom", "country_code":"GB", ... }
            $resp = Http::timeout(6)->retry(1, 150)->get("https://ipwho.is/{$ip}");

            if (!$resp->successful()) {
                return null;
            }

            $json = $resp->json();
            if (!is_array($json)) {
                return null;
            }

            if (isset($json['success']) && $json['success'] === false) {
                return null;
            }

            $name = trim((string) ($json['country'] ?? ''));
            $code = trim((string) ($json['country_code'] ?? ''));

            if ($name === '' && $code === '') {
                return null;
            }

            return [
                'name' => $name !== '' ? $name : null,
                'code' => $code !== '' ? strtoupper($code) : null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}