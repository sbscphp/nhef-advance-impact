<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 24px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .brand-name { font-size: 14px; font-weight: bold; color: #122168; margin: 0 0 4px 0; }
        .brand-meta { font-size: 10px; color: #6b7280; line-height: 1.5; }
        .meta-right { text-align: right; font-size: 10px; color: #374151; line-height: 1.6; }
        .logo { max-width: 90px; max-height: 48px; margin-bottom: 8px; }
        .title { font-size: 20px; font-weight: bold; color: #111827; margin: 4px 0 20px 0; }
        .body-content { font-size: 11px; line-height: 1.7; color: #1f2937; }
        .body-content h1, .body-content h2, .body-content h3, .body-content h4 { color: #111827; margin: 16px 0 6px 0; }
        .body-content p { margin: 0 0 10px 0; }
        .body-content ul, .body-content ol { margin: 0 0 10px 0; padding-left: 18px; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; text-align: center; line-height: 1.5; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 58%;">
                @if(!empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="Logo" class="logo">
                @endif
                <p class="brand-name">{{ $foundationName }}</p>
                <div class="brand-meta">
                    {{ $contactEmail }}<br>
                    {{ $website }}
                </div>
            </td>
            <td style="width: 42%;" class="meta-right">
                <strong>Prepared for:</strong> {{ $prospectName }}<br>
                <strong>Generated:</strong> {{ $generatedAt }}
            </td>
        </tr>
    </table>

    <p class="title">{{ $title }}</p>

    <div class="body-content">
        {!! $body !!}
    </div>

    <div class="footer">
        {{ $foundationName }} &middot; {{ $contactEmail }} &middot; {{ $website }}
    </div>
</body>
</html>
