<?php

namespace Plugins\ContactForm\Services;

use App\Cms\Core\Settings;
use Plugins\ContactForm\ContactSubmission;

class SubmissionSender
{
    public function attemptSend(ContactSubmission $s): void
    {
        // Shared hosting safe: never throw up to caller
        try {
            // If already sent, skip
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
                $retryMin = 5;   // safety minimum
            if ($maxTries <= 0)
                $maxTries = 10;

            // Stop retry after max tries (silent)
            if ((int) $s->attempts >= $maxTries) {
                $s->status = 'pending';
                $s->next_retry_at = null;
                $s->save();
                return;
            }

            // Not configured -> schedule retry silently
            if (!$enabled || $apiUrl === '' || $apiKey === '') {
                $this->markPendingAndSchedule($s, 'eDesk not configured/enabled', $retryMin);
                return;
            }

            $payload = [
                'name' => (string) $s->name,
                'email' => (string) $s->email,
                'subject' => (string) $s->subject,
                'message' => (string) $s->message,

                // required by your API
                'ip' => (string) ($s->ip ?? ''),
                'user_agent' => (string) ($s->user_agent ?? ''),

                // optional aliases (compat)
                'ip_address' => (string) ($s->ip ?? ''),
                'client_ip' => (string) ($s->ip ?? ''),
                'visitor_ip' => (string) ($s->ip ?? ''),
            ];

            app(EDeskClient::class)->send($apiUrl, $apiKey, $payload);

            $s->status = 'sent';
            $s->last_error = null;
            $s->next_retry_at = null;
            $s->save();
        } catch (\Throwable $e) {
            // Exponential backoff: retryMin * 2^(attempts) (capped)
            $settings = app(Settings::class);
            $baseRetryMin = (int) $settings->get('retry_minutes', 30, 'plugin:contact-form');
            if ($baseRetryMin <= 0)
                $baseRetryMin = 30;
            if ($baseRetryMin < 5)
                $baseRetryMin = 5;

            $attempts = (int) ($s->attempts ?? 0);
            $mult = 1 << min($attempts, 4); // 1,2,4,8,16 max
            $retry = min($baseRetryMin * $mult, 12 * 60); // cap at 12 hours

            $this->markPendingAndSchedule($s, $e->getMessage(), $retry);
        }
    }

    private function markPendingAndSchedule(ContactSubmission $s, string $error, int $retryMinutes): void
    {
        $s->status = 'pending';
        $s->attempts = (int) $s->attempts + 1;

        // keep last_error safe length for DB
        $error = trim($error);
        if (strlen($error) > 1500) {
            $error = substr($error, 0, 1500);
        }

        $s->last_error = $error;
        $s->next_retry_at = now()->addMinutes($retryMinutes);
        $s->save();
    }
}