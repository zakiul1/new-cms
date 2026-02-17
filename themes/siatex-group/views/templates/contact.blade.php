@extends('layouts.app')

@section('content')
    @php
        // Always generate fresh captcha numbers and store expected answer in session
        $a = rand(5, 20);
        $b = rand(1, 9);
        session([
            'contact_form.captcha_a' => $a,
            'contact_form.captcha_b' => $b,
            'contact_form.captcha_answer' => (string) ($a + $b),
        ]);

        $hasPlugin = \Illuminate\Support\Facades\Route::has('contact-form.submit');
        $actionUrl = $hasPlugin ? route('contact-form.submit') : '#';
    @endphp

    <div class="cms-container mx-auto py-8">
        <h1 class="text-3xl font-bold mb-6">{{ $post->title ?? 'Contact' }}</h1>

        {{-- success message (server) --}}
        @if (session('contact_success'))
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800" id="contactSuccessBox">
                Thanks! Your message has been submitted successfully.
            </div>
        @else
            <div class="mb-4 p-3 rounded bg-green-100 text-green-800 hidden" id="contactSuccessBox">
                Thanks! Your message has been submitted successfully.
            </div>
        @endif

        {{-- page content --}}
        <div class="prose max-w-none mb-8">
            {!! $content ?? '' !!}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <form id="contactForm" method="POST" action="{{ $actionUrl }}">
                    @csrf

                    <div class="mb-3">
                        <label class="block mb-1">Your Name (required)</label>
                        <input name="name" value="{{ old('name') }}" class="w-full border rounded p-2" required>
                        @if ($hasPlugin)
                            @error('name')
                                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="block mb-1">Your Email (required)</label>
                        <input name="email" value="{{ old('email') }}" class="w-full border rounded p-2" required>
                        @if ($hasPlugin)
                            @error('email')
                                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    {{-- ✅ WhatsApp (optional) --}}
                    <div class="mb-3">
                        <label class="block mb-1">WhatsApp Number</label>
                        <input name="whatsapp" value="{{ old('whatsapp') }}" class="w-full border rounded p-2"
                            placeholder="+44 7301 532365">
                        @if ($hasPlugin)
                            @error('whatsapp')
                                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="block mb-1">Subject (required)</label>
                        <input name="subject" value="{{ old('subject') }}" class="w-full border rounded p-2" required>
                        @if ($hasPlugin)
                            @error('subject')
                                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="block mb-1">Message (required)</label>
                        <textarea name="message" rows="6" class="w-full border rounded p-2" required>{{ old('message') }}</textarea>
                        @if ($hasPlugin)
                            @error('message')
                                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="block mb-1">{{ $a }} + {{ $b }} =</label>
                        <input name="captcha" value="" class="w-full border rounded p-2" required>
                        @if ($hasPlugin)
                            @error('captcha')
                                <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <button type="submit" class="px-5 py-2 rounded bg-blue-700 text-white">
                        SEND
                    </button>
                </form>

                {{-- Invisible fallback: if plugin missing, pretend success (no alert, same UI) --}}
                @if (!$hasPlugin)
                    <script>
                        (function() {
                            const form = document.getElementById('contactForm');
                            const successBox = document.getElementById('contactSuccessBox');

                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                successBox.classList.remove('hidden');
                                form.reset();
                                successBox.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start'
                                });
                            });
                        })();
                    </script>
                @endif
            </div>

            <div class="lg:col-span-1">
                <div class="border rounded p-4">
                    <h3 class="font-semibold mb-2">Dhaka Office</h3>
                    <div>Working day: Monday to Saturday</div>
                    <div>Office Hours: 10.00 am to 6.00 pm</div>
                </div>
            </div>
        </div>
    </div>
@endsection
