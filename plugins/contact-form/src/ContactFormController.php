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

        // ✅ Cart submit detection:
        // If cart_items (or cart_items_json) exists and is not empty => cart flow (no captcha needed).
        $cartRawInput = $request->input('cart_items', $request->input('cart_items_json', null));
        $isCartSubmit = is_string($cartRawInput) && trim($cartRawInput) !== '';

        // ✅ Validation rules differ by flow
        $rules = [
            'name' => ['required', 'string', 'max:99'],
            'email' => ['required', 'email', 'max:256'],
            'whatsapp' => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:5000'],

            // accept both keys (frontend may send either)
            'cart_items' => ['nullable', 'string'],
            'cart_items_json' => ['nullable', 'string'],
        ];

        if ($isCartSubmit) {
            // ✅ Cart flow: subject + captcha NOT required
            $rules['subject'] = ['nullable', 'string', 'max:256'];
            $rules['captcha'] = ['nullable', 'string', 'max:20'];
        } else {
            // ✅ Normal contact form: subject + captcha required
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

        // ✅ Always store submission first
        $ip = (string) $request->ip();

        $whatsapp = trim((string) ($data['whatsapp'] ?? ''));
        if ($whatsapp === '') {
            $whatsapp = null;
        }

        // ✅ clean message
        $message = trim((string) ($data['message'] ?? ''));

        // ✅ Website URL from CMS Settings (core.site_url)
        $siteUrl = (string) app(SettingsRepository::class)->get('core', 'site_url', (string) config('app.url'));
        $siteUrl = rtrim(trim($siteUrl), '/');

        // ✅ Reference page = page where user submitted from (best is HTTP Referer)
        $referenceUrl = trim((string) $request->headers->get('referer', ''));
        if ($referenceUrl === '') {
            $referenceUrl = $request->fullUrl();
        }

        // ✅ cart items (JSON) - store as array (model cast will handle)
        $cartItemsRaw = (string) ($data['cart_items'] ?? ($data['cart_items_json'] ?? ''));
        $cartItems = null;

        if (trim($cartItemsRaw) !== '') {
            try {
                $decoded = json_decode($cartItemsRaw, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    $normalized = [];

                    foreach ($decoded as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        $title = trim((string) ($item['title'] ?? ''));
                        $url = trim((string) ($item['url'] ?? ''));
                        $image = trim((string) ($item['image'] ?? ''));

                        if ($title === '' || $url === '') {
                            continue;
                        }

                        $normalized[] = [
                            'title' => $title,
                            'url' => $url,
                            'image' => $image !== '' ? $image : null,
                        ];
                    }

                    $cartItems = $normalized;
                }
            } catch (\Throwable $e) {
                $cartItems = null;
            }
        }

        // ✅ Subject handling:
        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            $subject = $isCartSubmit ? 'Get Price Request' : 'Contact Form';
        }

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

        // ✅ Try GeoIP + send (never blocks saving)
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

        // ✅ IMPORTANT: cart modal fetch() should get JSON always
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Submitted successfully.',
            ]);
        }

        return back()->with('contact_success', true);
    }
}