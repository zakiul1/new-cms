@php
    $footer = theme_footer_data();

    $bg = $footer['background_color'] ?? '#ffffff';
    $text = $footer['text_color'] ?? '#111827';
    $align = $footer['text_alignment'] ?? 'center';

    $siteName = theme_site_title();
    $tagline = theme_tagline();

    $beforeCopyright = (string) ($footer['before_copyright'] ?? '');
    $copyrightHtml = (string) ($footer['copyright_html'] ?? '');
    $secondLine = (string) ($footer['second_line'] ?? '');

    $footerMenu = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('footer');
@endphp

<footer class="cms-footer" style="background: {{ $bg }}; color: {{ $text }};">
    <div class="cms-container" style="padding: 42px 0;">
        <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start; justify-content:space-between;">
            <div style="min-width:240px; max-width:420px;">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.brand.before', '') !!}

                <div style="font-weight:800; font-size:18px; letter-spacing:-0.02em;">
                    {{ $siteName }}
                </div>

                @if ($tagline !== '')
                    <div style="margin-top:10px; line-height:1.6; opacity:.8;">
                        {{ $tagline }}
                    </div>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.brand.after', '') !!}
            </div>

            <div style="min-width:240px;">
                <div style="font-weight:700; margin-bottom:10px;">Links</div>

                <nav aria-label="Footer navigation" style="display:flex; flex-direction:column; gap:8px;">
                    @if (trim($footerMenu) !== '')
                        {!! $footerMenu !!}
                    @else
                        <a href="{{ url('/') }}" style="color:inherit; text-decoration:none; opacity:.85;">Home</a>
                    @endif
                </nav>
            </div>

            <div style="min-width:240px;">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.right', '') !!}
            </div>
        </div>

        <div class="copyright-text"
            style="margin-top:28px; padding-top:18px; border-top:1px solid rgba(0,0,0,.12); text-align: {{ $align }};">
            @if ($beforeCopyright !== '')
                <div style="margin-bottom:8px;">
                    {!! nl2br(e($beforeCopyright)) !!}
                </div>
            @endif

            @if ($copyrightHtml !== '')
                <div>
                    {!! $copyrightHtml !!}
                </div>
            @endif

            @if ($secondLine !== '')
                <div style="margin-top:8px; opacity:.85;">
                    {{ $secondLine }}
                </div>
            @endif

            <div style="margin-top:10px; opacity:.8;">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.footer.bottom', '') !!}
            </div>
        </div>
    </div>
</footer>
