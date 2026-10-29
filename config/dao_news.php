<?php

return [
    // Copyright-respecting manual directory; never fetch/parse articles or images.
    'mode' => 'curated_links_only',
    // Exact approved publisher/agency domains (+ the standard www form only).
    // Add more ONLY after a manual ownership, source and usage-policy review.
    // Subdomains OTHER than www are deliberately NOT trusted automatically.
    'trusted_hosts' => [
        'pia.gov.ph',             // Philippine Information Agency
        'pna.gov.ph',             // Philippine News Agency
        'pagasa.dost.gov.ph',     // PAGASA
        'phivolcs.dost.gov.ph',   // PHIVOLCS
        'dilg.gov.ph',
        'doh.gov.ph',
        'dswd.gov.ph',
        'deped.gov.ph',
        'gmanetwork.com',
        'abs-cbn.com',
        'inquirer.net',
        'philstar.com',
        'sunstar.com.ph',
    ],
    // Auto-hide unreviewed or stale links; Admin must reopen and check the original.
    'review_days' => 60,
    // Hide dated news after 90 days even when the approval was more recent.
    'max_story_age_days' => 90,
];
