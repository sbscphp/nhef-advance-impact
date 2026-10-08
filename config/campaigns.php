<?php

return [
    // How long before a campaign starts its public countdown timer appears; a campaign's own
    // timer_lead_hours overrides this.
    'default_timer_lead_hours' => (int) env('CAMPAIGN_TIMER_LEAD_HOURS', 72),
];
