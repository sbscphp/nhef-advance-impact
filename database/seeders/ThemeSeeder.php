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
                // White wordmark: the header band below is the brand green, so the dark/green
                // logo variant would be unreadable against it.
                'logo_url' => rtrim((string) config('app.url'), '/').'/assets/logo/nhef-nexus-white.png',
                'logo_path' => 'assets/logo/nhef-nexus-white.png',
                // Matches the admin frontend's own brand palette (nhef-nexus-fe-admin): #008751 is
                // its primary/link/button color throughout, used here for the header band too.
                'header_background_color' => '#008751',

                'primary_color' => '#008751',
                'secondary_color' => '#0F1F17',
                'accent_color' => '#008751',
                'link_color' => '#008751',
                'background_color' => '#F9FAFB',
                'surface_color' => '#FFFFFF',
                'text_color' => '#101828',
                'muted_text_color' => '#667085',
                'border_color' => '#EAECF0',
                'button_primary_bg' => '#008751',
                'button_primary_text' => '#FFFFFF',
                'button_secondary_bg' => '#344054',
                'button_secondary_text' => '#FFFFFF',

                'font_family' => "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
                'heading_font_family' => null,

                'support_email' => 'support@icoba.com',
                'footer_text' => 'Automated notification - please do not reply.',
                'footer_line_one' => 'Automated notification - please do not reply.',
                'footer_line_two' => "If you didn't expect this email, please ignore this message.",
            ]
        );
    }
}
