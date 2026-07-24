<?php

// Static branding/identity info for donation receipts and similar donor-facing PDF documents
// (see App\Services\Fundraising\DonationReceiptService). This is institution-wide, not
// per-record data, so it lives in config rather than a database table.
return [
    'foundation_name' => env('ORG_FOUNDATION_NAME', config('app.name')),
    'tax_id' => env('ORG_TAX_ID', ''),
    'contact_email' => env('ORG_CONTACT_EMAIL', ''),
    'website' => env('ORG_WEBSITE', config('app.frontend_url')),
    'executive_director_name' => env('ORG_EXECUTIVE_DIRECTOR_NAME', ''),
    'executive_director_title' => env('ORG_EXECUTIVE_DIRECTOR_TITLE', 'Executive Director'),
];
