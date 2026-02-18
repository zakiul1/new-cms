<?php

namespace Plugins\ContactForm;

use App\Cms\Core\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Plugins\ContactForm\Services\IpCountryResolver;
use Plugins\ContactForm\Services\SubmissionSender;
use Plugins\ContactForm\Support\Installer;

class ContactFormController extends Controller
{
    public function submit(Request $request)
    {
        Installer::ensureInstalled();

        // ✅ Detect cart submit if cart_items exists (string OR array) and not empty.
        $cartRawInput = $request->input('cart_items', $request->input('cart_items_json', null));
        $isCartSubmit = false;
        if (is_string($cartRawInput) && trim($cartRawInput) !== '')
            $isCartSubmit = true;
        if (is_array($cartRawInput) && !empty($cartRawInput))
            $isCartSubmit = true;

        // ✅ Validation rules differ by flow
        $rules = [
            'name' => ['required', 'string', 'max:99'],
            'email' => ['required', 'email', 'max:256'],
            'whatsapp' => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:5000'],

            // accept string or array for cart_items; accept both keys
            'cart_items' => ['nullable'],
            'cart_items_json' => ['nullable'],
        ];

        if ($isCartSubmit) {
            $rules['subject'] = ['nullable', 'string', 'max:256'];
            $rules['captcha'] = ['nullable', 'string', 'max:20'];
        } else {
            $rules['subject'] = ['required', 'string', 'max:256'];
            $rules['captcha'] = ['required', 'string', 'max:20'];
        }

        $data = $request->validate($rules);

        // ✅ Captcha check only for normal form submissions
        if (!$isCartSubmit) {
            $expected = (string) $request->session()->get('contact_form.captcha_answer', '');
            if ($expected === '' || trim((string) ($data['captcha'] ?? '')) !== $expected) {
                return back()
                    ->withErrors(['captcha' => 'Security answer is incorrect.'])
                    ->withInput();
            }
            $request->session()->forget('contact_form.captcha_answer');
        }

        // ✅ Website URL from CMS Settings (core.site_url)
        $siteUrl = (string) app(SettingsRepository::class)->get('core', 'site_url', (string) config('app.url'));
        $siteUrl = rtrim(trim($siteUrl), '/');

        // ✅ Reference page = where user submitted from (HTTP Referer best)
        $referenceUrl = trim((string) $request->headers->get('referer', ''));
        if ($referenceUrl === '') {
            $referenceUrl = $request->fullUrl();
        }

        // ✅ Get real client IP (works behind Cloudflare / proxies)
        $ip = $this->resolveClientIp($request);

        $whatsapp = trim((string) ($data['whatsapp'] ?? ''));
        if ($whatsapp === '')
            $whatsapp = null;

        $message = trim((string) ($data['message'] ?? ''));

        // ✅ Normalize cart items
        $cartItems = $this->normalizeCartItems(
            $data['cart_items'] ?? null,
            $data['cart_items_json'] ?? null,
            $siteUrl,
            $referenceUrl
        );

        // ✅ Subject handling
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            $subject = $isCartSubmit ? 'Get Price Request' : 'Contact Form';
        }
        // dd($cartItems);
        // ✅ Store submission first
        $submission = ContactSubmission::query()->create([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'subject' => $subject,
            'message' => $message,

            'whatsapp' => $whatsapp,

            'country_name' => null,
            'country_code' => null,

            'website_url' => $siteUrl !== '' ? $siteUrl : null,
            'reference_url' => $referenceUrl !== '' ? $referenceUrl : null,

            'cart_items' => $cartItems,

            'ip' => $ip,
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'status' => 'pending',
            'attempts' => 0,
        ]);

        // ✅ GeoIP + send (never blocks saving)
        try {
            $country = app(IpCountryResolver::class)->resolve($ip);

            $countryName = trim((string) ($country['name'] ?? ''));
            $countryCode = trim((string) ($country['code'] ?? ''));

            if ($countryName !== '' || $countryCode !== '') {
                $submission->country_name = $countryName !== '' ? $countryName : null;
                $submission->country_code = $countryCode !== '' ? strtoupper($countryCode) : null;
                $submission->save();
            }

            app(SubmissionSender::class)->attemptSend($submission);
        } catch (\Throwable $e) {
            // silent
        }

        // ✅ Cart modal fetch() should get JSON always
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Submitted successfully.',
            ]);
        }

        return back()->with('contact_success', true);
    }

    /**
     * Resolve real client IP behind proxies (Cloudflare/nginx).
     */
    private function resolveClientIp(Request $request): string
    {
        $candidates = [];

        // Cloudflare
        $cf = trim((string) $request->headers->get('cf-connecting-ip', ''));
        if ($cf !== '')
            $candidates[] = $cf;

        // Some CDNs / proxies
        $tci = trim((string) $request->headers->get('true-client-ip', ''));
        if ($tci !== '')
            $candidates[] = $tci;

        // Standard proxy header (first = client)
        $xff = trim((string) $request->headers->get('x-forwarded-for', ''));
        if ($xff !== '') {
            foreach (explode(',', $xff) as $part) {
                $part = trim($part);
                if ($part !== '')
                    $candidates[] = $part;
            }
        }

        // Fallback
        $candidates[] = (string) $request->ip();

        foreach ($candidates as $ip) {
            if ($this->isPublicIp($ip))
                return $ip;
        }

        return (string) $request->ip();
    }

    private function isPublicIp(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '')
            return false;

        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP) === false)
            return false;

        // Exclude private/reserved
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Normalize cart items.
     * Fixes: link/image missing in API because items were being skipped (url empty) or url/image not absolute.
     */
    private function normalizeCartItems($cartItemsValue, $cartItemsJsonValue, string $siteUrl, string $referenceUrl): ?array
    {
        $decoded = null;

        // If frontend sent array directly
        if (is_array($cartItemsValue)) {
            $decoded = $cartItemsValue;
        } elseif (is_string($cartItemsValue) && trim($cartItemsValue) !== '') {
            $decoded = $this->jsonToArrayOrNull($cartItemsValue);
        } elseif (is_array($cartItemsJsonValue)) {
            $decoded = $cartItemsJsonValue;
        } elseif (is_string($cartItemsJsonValue) && trim($cartItemsJsonValue) !== '') {
            $decoded = $this->jsonToArrayOrNull($cartItemsJsonValue);
        }

        if (!is_array($decoded))
            return null;

        $normalized = [];

        foreach ($decoded as $item) {
            if (!is_array($item))
                continue;

            $title = trim((string) ($item['title'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));
            $image = trim((string) ($item['image'] ?? ''));

            // Optional extra keys (helpful for debugging / future)
            $id = trim((string) ($item['id'] ?? ''));
            $type = trim((string) ($item['type'] ?? ''));

            // ✅ Do NOT drop item just because url is missing.
            // If url missing, use referenceUrl as fallback.
            if ($url === '')
                $url = $referenceUrl;

            // If title missing, try to fallback something (but still allow)
            if ($title === '')
                $title = 'Item';

            // ✅ Make url absolute
            $url = $this->absoluteUrl($url, $siteUrl);

            // ✅ Make image absolute (if provided)
            $image = $image !== '' ? $this->absoluteUrl($image, $siteUrl) : '';

            $normalized[] = [
                'title' => $title,
                'url' => $url,
                'image' => $image !== '' ? $image : null,
                // keep optional fields (won't break anything)
                'id' => $id !== '' ? $id : null,
                'type' => $type !== '' ? $type : null,
            ];
        }

        return $normalized;
    }

    private function jsonToArrayOrNull(string $json): ?array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function absoluteUrl(string $maybeUrl, string $siteUrl): string
    {
        $u = trim($maybeUrl);
        if ($u === '')
            return '';

        // Already absolute
        if (preg_match('~^https?://~i', $u))
            return $u;

        // Protocol-relative //example.com/...
        if (str_starts_with($u, '//'))
            return 'https:' . $u;

        // If it is a path /something
        if (str_starts_with($u, '/')) {
            return rtrim($siteUrl, '/') . $u;
        }

        // Otherwise treat as relative
        return rtrim($siteUrl, '/') . '/' . ltrim($u, '/');
    }
}