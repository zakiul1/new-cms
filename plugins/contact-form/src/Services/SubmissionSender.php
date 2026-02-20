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

            if ($retryMin <= 0) {
                $retryMin = 30;
            }
            if ($retryMin < 5) {
                $retryMin = 5;
            }
            if ($maxTries <= 0) {
                $maxTries = 10;
            }

            if ((int) $s->attempts >= $maxTries) {
                // Keep pending, but stop scheduling retries
                $s->status = 'pending';
                $s->next_retry_at = null;
                $s->save();
                return;
            }

            if (!$enabled || $apiUrl === '' || $apiKey === '') {
                $this->markPendingAndSchedule($s, 'eDesk not configured/enabled', $retryMin);
                return;
            }

            // ✅ Pull subject/message/cart from encrypted payload (temporary)
            $payloadData = is_array($s->payload ?? null) ? $s->payload : [];

            $rawSubject = trim((string) ($payloadData['subject'] ?? $s->subject ?? ''));
            $rawMessage = trim((string) ($payloadData['message'] ?? $s->message ?? ''));

            // ✅ Country: prefer name, fallback to code
            $countryName = trim((string) ($s->country_name ?? ''));
            $countryCode = trim((string) ($s->country_code ?? ''));
            $country = $countryName !== '' ? $countryName : ($countryCode !== '' ? $countryCode : 'Unknown');

            // Normal form extra fields
            $date = $s->created_at ? $s->created_at->format('F j, Y') : now()->format('F j, Y');

            $website = trim((string) ($s->website_url ?? ''));
            if ($website === '') {
                $website = 'Unknown';
            }

            $reference = trim((string) ($s->reference_url ?? ''));
            if ($reference === '') {
                $reference = 'Unknown';
            }

            $whatsapp = trim((string) ($s->whatsapp ?? ''));
            if ($whatsapp === '') {
                $whatsapp = 'Not given';
            }

            // ✅ Real IP + UA for API safety
            $realIp = trim((string) ($s->ip ?? ''));
            if ($realIp === '') {
                try {
                    $realIp = (string) (request()->ip() ?? '');
                } catch (\Throwable $e) {
                    $realIp = '';
                }
            }
            if ($realIp === '') {
                $realIp = '0.0.0.0';
            }

            $realUa = trim((string) ($s->user_agent ?? ''));
            if ($realUa === '') {
                try {
                    $realUa = (string) (request()->userAgent() ?? '');
                } catch (\Throwable $e) {
                    $realUa = '';
                }
            }

            // ✅ Cart items: prefer payload cart_items (temporary), fallback to legacy column
            $cartItems = $payloadData['cart_items'] ?? $s->cart_items;
            if (!is_array($cartItems)) {
                $cartItems = [];
            }

            // ✅ Normalize / limit cart items
            $normalizedItems = [];
            foreach ($cartItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $title = trim((string) ($item['title'] ?? ''));
                $url = trim((string) ($item['url'] ?? ''));
                $image = trim((string) ($item['image'] ?? ''));

                if ($title === '') {
                    $title = 'Item';
                }

                // If url missing, fallback so item isn't dropped
                if ($url === '') {
                    $url = $reference !== 'Unknown' ? $reference : ($website !== 'Unknown' ? $website : '');
                }

                if (mb_strlen($title) > 200) {
                    $title = mb_substr($title, 0, 200);
                }
                if (mb_strlen($url) > 1000) {
                    $url = mb_substr($url, 0, 1000);
                }
                if (mb_strlen($image) > 1000) {
                    $image = mb_substr($image, 0, 1000);
                }

                $normalizedItems[] = [
                    'title' => $title,
                    'url' => $url,
                    'image' => $image !== '' ? $image : null,
                ];

                if (count($normalizedItems) >= 25) {
                    break;
                }
            }

            // ✅ Detect cart submit
            $isCartSubmit = count($normalizedItems) > 0;

            // ✅ Ensure we always have a subject
            if ($rawSubject === '') {
                $rawSubject = $isCartSubmit ? 'Get Price Request' : 'Contact Form';
            }

            // ✅ Build items table HTML
            $itemsTableHtml = '';
            if ($isCartSubmit) {
                // table-layout fixed helps ellipsis work reliably in many viewers
                $itemsTableHtml .= '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse:collapse;width:100%;table-layout:fixed;">';
                $itemsTableHtml .= '<tbody>';

                foreach ($normalizedItems as $item) {
                    $title = trim((string) ($item['title'] ?? ''));
                    $url = trim((string) ($item['url'] ?? ''));
                    $image = trim((string) ($item['image'] ?? ''));

                    if ($title === '') {
                        $title = 'Item';
                    }

                    $itemsTableHtml .= '<tr>';

                    // left image (clickable)
                    $itemsTableHtml .= '<td style="width:120px;vertical-align:middle;">';
                    if ($image !== '') {
                        if ($url !== '') {
                            $itemsTableHtml .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer" style="display:inline-block;">'
                                . '<img src="' . e($image) . '" alt="" style="width:110px;height:auto;display:block;">'
                                . '</a>';
                        } else {
                            $itemsTableHtml .= '<img src="' . e($image) . '" alt="" style="width:110px;height:auto;display:block;">';
                        }
                    } else {
                        $itemsTableHtml .= '&nbsp;';
                    }
                    $itemsTableHtml .= '</td>';

                    // right title link (ellipsis)
                    $itemsTableHtml .= '<td style="vertical-align:middle;">';

                    $titleHtml =
                        '<span style="display:inline-block;max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;">'
                        . e($title) .
                        '</span>';

                    if ($url !== '') {
                        $itemsTableHtml .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;color:#000;display:inline-block;max-width:420px;">'
                            . $titleHtml .
                            '</a>';
                    } else {
                        $itemsTableHtml .= $titleHtml;
                    }

                    $itemsTableHtml .= '</td>';
                    $itemsTableHtml .= '</tr>';
                }

                $itemsTableHtml .= '</tbody></table>';
            }

            // ✅ Build message + payload
            if ($isCartSubmit) {
                // CART format:
                // Name/Email/Country/Whatsapp + 2 breaks + message + 2 breaks + items table
                $formattedMessage =
                    'Name: ' . e((string) $s->name) . '<br>' .
                    'Email: ' . e((string) $s->email) . '<br>' .
                    'Country: ' . e($country) . '<br>' .
                    'WhatsApp: ' . e($whatsapp) . '<br><br>' .
                    nl2br(e($rawMessage)) . '<br><br>' .
                    $itemsTableHtml;

                $payload = [
                    'name' => (string) $s->name,
                    'email' => (string) $s->email,
                    'subject' => $rawSubject,
                    'message' => $formattedMessage,
                    'whatsapp' => $whatsapp,
                    'country' => $country,
                    'cart_items' => $normalizedItems,

                    // required by eDesk
                    'ip' => $realIp,
                    'user_agent' => $realUa,

                    // keep keys for API compatibility
                    'date' => '',
                    'website' => '',
                    'reference_page' => '',

                    // aliases
                    'ip_address' => $realIp,
                    'client_ip' => $realIp,
                    'visitor_ip' => $realIp,
                ];
            } else {
                // CONTACT FORM format
                $formattedMessage =
                    'Name: ' . e((string) $s->name) . '<br>' .
                    'Email: ' . e((string) $s->email) . '<br>' .
                    'WhatsApp: ' . e($whatsapp) . '<br>' .
                    'Country: ' . e($country) . '<br>' .
                    'Date: ' . e($date) . '<br><br>' .
                    'Website: ' . e($website) . '<br>' .
                    'Referance Page: ' . e($reference) . '<br><br>' .
                    nl2br(e($rawMessage));

                $payload = [
                    'name' => (string) $s->name,
                    'email' => (string) $s->email,
                    'subject' => $rawSubject,
                    'message' => $formattedMessage,
                    'whatsapp' => $whatsapp,
                    'country' => $country,
                    'date' => $date,
                    'website' => $website,
                    'reference_page' => $reference,

                    'ip' => $realIp,
                    'user_agent' => $realUa,
                    'ip_address' => $realIp,
                    'client_ip' => $realIp,
                    'visitor_ip' => $realIp,
                ];
            }

            app(EDeskClient::class)->send($apiUrl, $apiKey, $payload);

            $s->status = 'sent';
            $s->sent_at = now(); // ✅ used by prune rule (delete sent after 24h)
            $s->last_error = null;
            $s->next_retry_at = null;
            $s->save();
        } catch (\Throwable $e) {
            $settings = app(Settings::class);
            $baseRetryMin = (int) $settings->get('retry_minutes', 30, 'plugin:contact-form');

            if ($baseRetryMin <= 0) {
                $baseRetryMin = 30;
            }
            if ($baseRetryMin < 5) {
                $baseRetryMin = 5;
            }

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