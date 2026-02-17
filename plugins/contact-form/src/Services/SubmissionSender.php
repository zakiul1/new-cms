<?php

namespace Plugins\ContactForm\Services;

use App\Cms\Core\Settings;
use Plugins\ContactForm\ContactSubmission;

class SubmissionSender
{
    public function attemptSend(ContactSubmission $s): void
    {
        try {
            if (($s->status ?? '') === 'sent') {
                return;
            }

            $settings = app(Settings::class);

            $enabled = (bool) $settings->get('edesk_enabled', true, 'plugin:contact-form');
            $apiUrl = trim((string) $settings->get('edesk_api_url', '', 'plugin:contact-form'));
            $apiKey = trim((string) $settings->get('edesk_api_key', '', 'plugin:contact-form'));
            $retryMin = (int) $settings->get('retry_minutes', 30, 'plugin:contact-form');
            $maxTries = (int) $settings->get('max_attempts', 10, 'plugin:contact-form');

            if ($retryMin <= 0)
                $retryMin = 30;
            if ($retryMin < 5)
                $retryMin = 5;
            if ($maxTries <= 0)
                $maxTries = 10;

            if ((int) $s->attempts >= $maxTries) {
                $s->status = 'pending';
                $s->next_retry_at = null;
                $s->save();
                return;
            }

            if (!$enabled || $apiUrl === '' || $apiKey === '') {
                $this->markPendingAndSchedule($s, 'eDesk not configured/enabled', $retryMin);
                return;
            }

            // ✅ Country should be IP-based country NAME (already resolved earlier)
            $countryName = trim((string) ($s->country_name ?? ''));
            $countryCode = trim((string) ($s->country_code ?? ''));
            $country = $countryName !== '' ? $countryName : ($countryCode !== '' ? $countryCode : 'Unknown');

            // ✅ Date/Website/Reference ONLY for normal contact form (not cart)
            $date = $s->created_at ? $s->created_at->format('F j, Y') : now()->format('F j, Y');

            $website = trim((string) ($s->website_url ?? ''));
            if ($website === '')
                $website = 'Unknown';

            $reference = trim((string) ($s->reference_url ?? ''));
            if ($reference === '')
                $reference = 'Unknown';

            $whatsapp = trim((string) ($s->whatsapp ?? ''));
            if ($whatsapp === '')
                $whatsapp = 'Not given';

            // ✅ Real IP and UA for API (required)
            $realIp = trim((string) ($s->ip ?? ''));
            if ($realIp === '') {
                try {
                    $realIp = (string) (request()->ip() ?? '');
                } catch (\Throwable $e) {
                    $realIp = '';
                }
            }
            if ($realIp === '')
                $realIp = '0.0.0.0';

            $realUa = trim((string) ($s->user_agent ?? ''));
            if ($realUa === '') {
                try {
                    $realUa = (string) (request()->userAgent() ?? '');
                } catch (\Throwable $e) {
                    $realUa = '';
                }
            }

            // ✅ Cart items (array cast from model)
            $cartItems = $s->cart_items;
            if (!is_array($cartItems)) {
                $cartItems = [];
            }

            // ✅ Normalize / limit cart items (safety)
            $normalizedItems = [];
            foreach ($cartItems as $item) {
                if (!is_array($item))
                    continue;

                $title = trim((string) ($item['title'] ?? ''));
                $url = trim((string) ($item['url'] ?? ''));
                $image = trim((string) ($item['image'] ?? ''));

                if ($title === '' || $url === '')
                    continue;

                if (mb_strlen($title) > 200)
                    $title = mb_substr($title, 0, 200);
                if (mb_strlen($url) > 1000)
                    $url = mb_substr($url, 0, 1000);
                if (mb_strlen($image) > 1000)
                    $image = mb_substr($image, 0, 1000);

                $normalizedItems[] = [
                    'title' => $title,
                    'url' => $url,
                    'image' => $image !== '' ? $image : null,
                ];

                if (count($normalizedItems) >= 25)
                    break;
            }

            // ✅ Detect cart submit
            $isCartSubmit = count($normalizedItems) > 0;

            // ✅ Build items table HTML (only for cart submits)
            $itemsTableHtml = '';
            if ($isCartSubmit) {
                $itemsTableHtml .= '<b>Items</b><br>';
                $itemsTableHtml .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">';
                $itemsTableHtml .= '<tbody>';

                foreach ($normalizedItems as $item) {
                    $title = (string) ($item['title'] ?? '');
                    $url = (string) ($item['url'] ?? '');
                    $image = (string) ($item['image'] ?? '');

                    if ($title === '' || $url === '')
                        continue;

                    $itemsTableHtml .= '<tr>';

                    $itemsTableHtml .= '<td style="width:70px;vertical-align:top;">';
                    if ($image !== '') {
                        $itemsTableHtml .= '<img src="' . e($image) . '" alt="" style="width:60px;height:auto;display:block;">';
                    } else {
                        $itemsTableHtml .= '&nbsp;';
                    }
                    $itemsTableHtml .= '</td>';

                    $itemsTableHtml .= '<td style="vertical-align:top;">';
                    $itemsTableHtml .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($title) . '</a>';
                    $itemsTableHtml .= '</td>';

                    $itemsTableHtml .= '</tr>';
                }

                $itemsTableHtml .= '</tbody></table><br><br>';
            }

            // ✅ Build message + payload differently for Cart vs Contact form
            if ($isCartSubmit) {
                // CART: send only requested fields in message (no date/website/reference)
                $formattedMessage =
                    'Name: ' . e((string) $s->name) . '<br>' .
                    'Email: ' . e((string) $s->email) . '<br>' .
                    'WhatsApp: ' . e($whatsapp) . '<br>' .
                    'Country: ' . e($country) . '<br><br>' .
                    nl2br(e(trim((string) $s->message))) . '<br><br>' .
                    $itemsTableHtml;

                $payload = [
                    'name' => (string) $s->name,
                    'email' => (string) $s->email,
                    'subject' => (string) $s->subject,
                    'message' => $formattedMessage,
                    'whatsapp' => $whatsapp,
                    'country' => $country,
                    'cart_items' => $normalizedItems,

                    // ✅ REQUIRED: ip cannot be null on eDesk DB
                    'ip' => $realIp,
                    'user_agent' => $realUa,

                    // ✅ keep these keys for API compatibility (empty is OK)
                    'date' => '',
                    'website' => '',
                    'reference_page' => '',

                    // ✅ aliases (optional)
                    'ip_address' => $realIp,
                    'client_ip' => $realIp,
                    'visitor_ip' => $realIp,
                ];
            } else {
                // CONTACT FORM: include date/website/reference
                $formattedMessage =
                    'Name: ' . e((string) $s->name) . '<br>' .
                    'Email: ' . e((string) $s->email) . '<br>' .
                    'WhatsApp: ' . e($whatsapp) . '<br>' .
                    'Country: ' . e($country) . '<br>' .
                    'Date: ' . e($date) . '<br><br>' .
                    'Website: ' . e($website) . '<br>' .
                    'Referance Page: ' . e($reference) . '<br><br>' .
                    nl2br(e(trim((string) $s->message)));

                $payload = [
                    'name' => (string) $s->name,
                    'email' => (string) $s->email,
                    'subject' => (string) $s->subject,
                    'message' => $formattedMessage,
                    'whatsapp' => $whatsapp,
                    'country' => $country,
                    'date' => $date,
                    'website' => $website,
                    'reference_page' => $reference,

                    // ✅ also send ip/ua for API safety
                    'ip' => $realIp,
                    'user_agent' => $realUa,
                    'ip_address' => $realIp,
                    'client_ip' => $realIp,
                    'visitor_ip' => $realIp,
                ];
            }

            app(EDeskClient::class)->send($apiUrl, $apiKey, $payload);

            $s->status = 'sent';
            $s->last_error = null;
            $s->next_retry_at = null;
            $s->save();
        } catch (\Throwable $e) {
            $settings = app(Settings::class);
            $baseRetryMin = (int) $settings->get('retry_minutes', 30, 'plugin:contact-form');

            if ($baseRetryMin <= 0)
                $baseRetryMin = 30;
            if ($baseRetryMin < 5)
                $baseRetryMin = 5;

            $attempts = (int) ($s->attempts ?? 0);
            $mult = 1 << min($attempts, 4);
            $retry = min($baseRetryMin * $mult, 12 * 60);

            $this->markPendingAndSchedule($s, $e->getMessage(), $retry);
        }
    }

    private function markPendingAndSchedule(ContactSubmission $s, string $error, int $retryMinutes): void
    {
        $s->status = 'pending';
        $s->attempts = (int) $s->attempts + 1;

        $error = trim($error);
        if (strlen($error) > 1500) {
            $error = substr($error, 0, 1500);
        }

        $s->last_error = $error;
        $s->next_retry_at = now()->addMinutes($retryMinutes);
        $s->save();
    }
}