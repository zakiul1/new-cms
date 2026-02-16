<?php

namespace Plugins\ContactForm;

use App\Cms\Core\Settings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Plugins\ContactForm\Services\SubmissionSender;
use Plugins\ContactForm\Support\Installer;

class CronController extends Controller
{
    public function run(Request $request)
    {
        // Shared hosting safe: never break, never leak details
        try {
            // Avoid long execution on shared hosting
            @set_time_limit(20);

            // Ensure DB exists (silent inside Installer)
            Installer::ensureInstalled();

            $settings = app(Settings::class);

            $token = trim((string) $settings->get('cron_token', '', 'plugin:contact-form'));
            $given = trim((string) $request->query('token', ''));

            // If token not set or mismatch -> return 200 OK silently (better for cPanel cron)
            if ($token === '' || $given === '' || !hash_equals($token, $given)) {
                return response('OK', 200);
            }

            // If API disabled, do nothing (silent)
            $enabled = (bool) $settings->get('edesk_enabled', true, 'plugin:contact-form');
            if (!$enabled) {
                return response('OK', 200);
            }

            $limit = (int) $settings->get('cron_batch', 10, 'plugin:contact-form');
            if ($limit <= 0)
                $limit = 10;
            if ($limit > 50)
                $limit = 50; // safety cap

            $items = ContactSubmission::query()
                ->where('status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('next_retry_at')
                        ->orWhere('next_retry_at', '<=', now());
                })
                ->orderByRaw('next_retry_at IS NULL DESC') // null first (immediate)
                ->orderBy('next_retry_at', 'asc')
                ->limit($limit)
                ->get();

            if ($items->isEmpty()) {
                return response('OK: processed 0', 200);
            }

            $sender = app(SubmissionSender::class);

            foreach ($items as $s) {
                $sender->attemptSend($s);
            }

            return response('OK: processed ' . $items->count(), 200);
        } catch (\Throwable $e) {
            // Silent failure by design
            return response('OK', 200);
        }
    }
}