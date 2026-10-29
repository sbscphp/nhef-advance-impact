<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    public function run(): void
    {
        Theme::query()->update(['is_default' => false]);

        Theme::updateOrCreate(
            ['name' => 'Default Theme'],
            [
                'is_default' => true,
                'brand_name' => config('app.name'),
                // White wordmark: the header band below is the brand navy, so the colored/dark
                // logo variant would be unreadable against it.
                'logo_url' => rtrim((string) config('app.url'), '/').'/assets/logo/advance-impact-white.png',
                'logo_path' => 'assets/logo/advance-impact-white.png',
                // Matches the Advance Impact frontends' own brand palette
                // (nhef-advanced-inpact-fe-admin/-client): #052A6A is the primary/link/button
                // color actively used throughout both (confirmed by usage count in each bundle);
                // the provided logo artwork is also drawn for exactly this navy, not green.
                'header_background_color' => '#052A6A',

                'primary_color' => '#052A6A',
                'secondary_color' => '#001130',
                'accent_color' => '#052A6A',
                'link_color' => '#052A6A',
                'background_color' => '#F9FAFB',
                'surface_color' => '#FFFFFF',
                'text_color' => '#101828',
                'muted_text_color' => '#667085',
                'border_color' => '#EAECF0',
                'button_primary_bg' => '#052A6A',
                'button_primary_text' => '#FFFFFF',
                'button_secondary_bg' => '#344054',
                'button_secondary_text' => '#FFFFFF',

                'font_family' => "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
                'heading_font_family' => null,

                'support_email' => 'support@nhefadvancedimpact.com',
                'footer_text' => 'Automated notification - please do not reply.',
                'footer_line_one' => 'Automated notification - please do not reply.',
                'footer_line_two' => "If you didn't expect this email, please ignore this message.",
            ]
        );
    }
}
