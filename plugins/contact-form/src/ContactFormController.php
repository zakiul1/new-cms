<?php

namespace Plugins\ContactForm;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Plugins\ContactForm\Services\SubmissionSender;
use Plugins\ContactForm\Support\Installer;

class ContactFormController extends Controller
{
    public function submit(Request $request)
    {
        // Always ensure DB exists (no migration needed)
        Installer::ensureInstalled();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:99'],
            'email' => ['required', 'email', 'max:256'],
            'subject' => ['required', 'string', 'max:256'],
            'message' => ['required', 'string', 'max:5000'],
            'captcha' => ['required', 'string', 'max:20'],
        ]);

        $expected = (string) $request->session()->get('contact_form.captcha_answer', '');
        if ($expected === '' || trim((string) $data['captcha']) !== $expected) {
            return back()
                ->withErrors(['captcha' => 'Security answer is incorrect.'])
                ->withInput();
        }

        $request->session()->forget('contact_form.captcha_answer');

        try {
            $submission = ContactSubmission::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'ip' => (string) $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'status' => 'pending',
                'attempts' => 0,
            ]);

            // Immediate try (no queue worker needed)
            app(SubmissionSender::class)->attemptSend($submission);
        } catch (\Throwable $e) {
            // Silent: still show success to user
        }

        return back()->with('contact_success', true);
    }
}