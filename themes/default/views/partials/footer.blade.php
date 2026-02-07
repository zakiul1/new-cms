@php
    $o = theme_options();

    // Simple footer options (safe defaults)
    $bg = $o['footer_bg'] ?? '#0b1220';
    $text = $o['footer_text'] ?? '#ffffff';
    $muted = $o['footer_muted'] ?? 'rgba(255,255,255,.75)';

    $year = (int) date('Y');
    $siteName = config('app.name', 'CMS');

    // Optional: dynamic menu location for footer (if you create assignment)
    $footerMenu = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('footer');
@endphp

<footer class="cms-footer" style="background: {{ $bg }}; color: {{ $text }};">
    <div class="cms-container" style="padding: 42px 0;">
        {{-- Top area --}}
        <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start; justify-content:space-between;">
            {{-- Brand / about --}}
            <div style="min-width:240px; max-width:420px;">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.brand.before', '') !!}

                <div style="font-weight:800; font-size:18px; letter-spacing:-0.02em;">
                    {{ $siteName }}
                </div>

                <div style="margin-top:10px; color: {{ $muted }}; line-height:1.6;">
                    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters(
                        'theme.footer.about',
                        e($o['footer_about'] ?? 'A premium CMS theme footer. You can customize this text from Theme Options later.')
                    ) !!}
                </div>

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.brand.after', '') !!}
            </div>

            {{-- Links --}}
            <div style="min-width:240px;">
                <div style="font-weight:700; margin-bottom:10px;">Links</div>

                <nav aria-label="Footer navigation" style="display:flex; flex-direction:column; gap:8px;">
                    @if (trim($footerMenu) !== '')
                        {!! $footerMenu !!}
                    @else
                        <a href="{{ url('/') }}" style="color:{{ $muted }}; text-decoration:none;">Home</a>
                    @endif
                </nav>
            </div>

            {{-- Right widget area (plugins / theme) --}}
            <div style="min-width:240px;">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.right', '') !!}
            </div>
        </div>

        {{-- Bottom bar --}}
        <div style="margin-top:28px; padding-top:18px; border-top:1px solid rgba(255,255,255,.12); display:flex; gap:14px; flex-wrap:wrap; justify-content:space-between; align-items:center;">
            <div style="color: {{ $muted }};">
                © {{ $year }} {{ $siteName }}. All rights reserved.
            </div>

            <div style="color: {{ $muted }};">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.bottom', '') !!}
            </div>
        </div>
    </div>
</footer>
