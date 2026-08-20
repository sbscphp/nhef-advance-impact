@php
    use App\Helpers\GeneralHelper;
    use App\Services\Theme\ThemeResolver;

    $theme = $theme ?? app(ThemeResolver::class)->resolveForMail();
    $logoPath = GeneralHelper::resolveMailLogoPath($theme);
    // Only trust a genuinely embeddable local file. A `logo_url` pointing at an unreachable
    // host (e.g. APP_URL=localhost in dev, or a stale/never-uploaded asset) still renders an
    // <img> tag; when that image 404s, mail clients fall back to unstyled alt text, which can
    // end up unreadable against a dark header. If there's no local file to embed, skip the
    // image entirely and render a styled text wordmark instead (see $hasLogo below).
    $hasLogo = is_file($logoPath);
    $logoUrl = $hasLogo && isset($message) && method_exists($message, 'embed')
        ? $message->embed($logoPath)
        : GeneralHelper::resolveMailLogoUrl($theme);
    $brandSubtitle = $brandSubtitle ?? '';
    $footerName = $footerName ?? $theme->footerBrandName();
    $footerLineOne = $footerLineOne ?? $theme->footerLineOne();
    $footerLineTwo = $footerLineTwo ?? $theme->footerLineTwo();
    $accentColor = $theme->accentColor();
    $linkColor = $theme->linkColor();
    $brandName = $theme->brand_name ?? config('app.name');

    // Pick readable text for the header band based on its actual brightness, rather than
    // assuming it's always dark.
    $headerHex = ltrim((string) $theme->header_background_color, '#');
    if (strlen($headerHex) === 3) {
        $headerHex = $headerHex[0].$headerHex[0].$headerHex[1].$headerHex[1].$headerHex[2].$headerHex[2];
    }
    $headerLuminance = strlen($headerHex) === 6
        ? (0.299 * hexdec(substr($headerHex, 0, 2)) + 0.587 * hexdec(substr($headerHex, 2, 2)) + 0.114 * hexdec(substr($headerHex, 4, 2))) / 255
        : 0;
    $headerTextColor = $headerLuminance > 0.55 ? '#111111' : '#FFFFFF';
    $headerSubtitleColor = $headerLuminance > 0.55 ? 'rgba(17,17,17,0.75)' : 'rgba(255,255,255,0.85)';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? $theme->brand_name ?? config('app.name') }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: {{ $theme->background_color }};
            font-family: {{ $theme->font_family }};
            color: {{ $theme->text_color }};
        }
        @media screen and (max-width: 600px) {
            .wrapper { width: 100% !important; }
            .content { padding: 24px !important; }
        }
        a { color: {{ $linkColor }}; }
    </style>
</head>
<body>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:{{ $theme->background_color }}; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" class="wrapper" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background-color:{{ $theme->surface_color }}; border-radius:16px; overflow:hidden; box-shadow:0 12px 30px rgba(18,33,104,0.08);">
                    <tr>
                        <td style="padding:32px 32px 24px 32px; text-align:center; background: {{ $theme->header_background_color }};">
                            @if($hasLogo)
                                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" width="200" style="display:block; margin:0 auto; max-width: 100%; height: auto;">
                            @else
                                <span style="display:block; font-size:26px; font-weight:700; letter-spacing:0.5px; color:{{ $headerTextColor }};">{{ $brandName }}</span>
                            @endif
                            @if(trim($brandSubtitle) !== '')
                                <p style="margin:8px 0 0 0; font-size:14px; color:{{ $headerSubtitleColor }};">{{ $brandSubtitle }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="content" style="padding:32px 40px;">
                            @isset($headline)
                                <h2 style="margin:0 0 12px 0; font-size:20px; color:{{ $accentColor }};">{{ $headline }}</h2>
                            @endisset
                            @isset($lead)
                                <p style="margin:0 0 20px 0; line-height:1.6; color:{{ $theme->text_color }};">{!! $lead !!}</p>
                            @endisset

                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 32px; background-color:{{ $theme->background_color }}; text-align:center; font-size:12px; line-height:1.6; color:{{ $theme->muted_text_color }};">
                            <strong style="display:block; color:{{ $accentColor }};">{{ $footerName }}</strong>
                            <span>{{ $footerLineOne }}</span><br>
                            <span>{{ $footerLineTwo }}</span>
                            @if(filled($theme->support_email))
                                <br><span>Support: <a href="mailto:{{ $theme->support_email }}" style="color:{{ $linkColor }};">{{ $theme->support_email }}</a></span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
