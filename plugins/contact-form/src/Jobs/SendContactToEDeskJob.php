<?php

namespace Plugins\ContactForm\Jobs;

use App\Cms\Core\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Plugins\ContactForm\ContactSubmission;
use Plugins\ContactForm\Services\EDeskClient;

class SendContactToEDeskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $submissionId)
    {
    }

    public function handle(): void
    {
        $settings = app(Settings::class);

        $enabled = (bool) $settings->get('edesk_enabled', true, 'plugin:contact-form');
        $apiUrl = trim((string) $settings->get('edesk_api_url', '', 'plugin:contact-form'));
        $apiKey = trim((string) $settings->get('edesk_api_key', '', 'plugin:contact-form'));
        $retryMin = (int) $settings->get('retry_minutes', 30, 'plugin:contact-form');
        $maxTries = (int) $settings->get('max_attempts', 10, 'plugin:contact-form');

        if ($retryMin <= 0)
            $retryMin = 30;
        if ($maxTries <= 0)
            $maxTries = 10;

        $s = ContactSubmission::query()->find($this->submissionId);
        if (!$s)
            return;

        // Stop retrying after too many attempts (silent)
        if ((int) $s->attempts >= $maxTries) {
            $s->status = 'pending';
            $s->last_error = trim((string) $s->last_error) ?: 'Max attempts reached';
            $s->next_retry_at = null;
            $s->save();
            return;
        }

        // If API not configured/enabled, keep pending silently + schedule retry
        if (!$enabled || $apiUrl === '' || $apiKey === '') {
            $s->status = 'pending';
            $s->attempts = (int) $s->attempts + 1;
            $s->last_error = 'eDesk not configured/enabled';
            $s->next_retry_at = now()->addMinutes($retryMin);
            $s->save();

            self::dispatch($s->id)->delay($s->next_retry_at);
            return;
        }

        try {
            // ✅ FIX: include IP + UA in payload (and common aliases)
            $payload = [
                'name' => $s->name,
                'email' => $s->email,
                'subject' => $s->subject,
                'message' => $s->message,

                'ip' => $s->ip,
                'user_agent' => $s->user_agent,

                // optional aliases (some APIs use different keys)
                'ip_address' => $s->ip,
                'client_ip' => $s->ip,
                'visitor_ip' => $s->ip,
            ];

            app(EDeskClient::class)->send($apiUrl, $apiKey, $payload);

            $s->status = 'sent';
            $s->last_error = null;
            $s->next_retry_at = null;
            $s->save();
        } catch (\Throwable $e) {
            $s->status = 'pending';
            $s->attempts = (int) $s->attempts + 1;
            $s->last_error = $e->getMessage();
            $s->next_retry_at = now()->addMinutes($retryMin);
            $s->save();

            self::dispatch($s->id)->delay($s->next_retry_at);
        }
    }
}