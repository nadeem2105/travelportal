<?php

/*
| Ad Creative Studio configuration. Kept out of code so fonts, storage disk and
| limits can be tuned per environment without edits. Fonts must be TTF files GD
| can read (imagettftext). On Windows/XAMPP the system fonts are used by default;
| override CREATIVE_FONT_* to bundle your own in resources/fonts.
*/

return [
    // Storage disk for generated creatives + thumbnails (reuses existing disks).
    'disk' => env('CREATIVE_DISK', 'public'),
    'folder' => 'marketing/creatives',

    // TrueType fonts for GD text overlays. Falls back gracefully if unreadable.
    'fonts' => [
        'bold' => env('CREATIVE_FONT_BOLD', 'C:\\Windows\\Fonts\\arialbd.ttf'),
        'regular' => env('CREATIVE_FONT_REGULAR', 'C:\\Windows\\Fonts\\arial.ttf'),
    ],

    // Per-user / per-day AI image generation cap (cost control). 0 = unlimited.
    'daily_image_limit' => (int) env('CREATIVE_DAILY_IMAGE_LIMIT', 100),

    // JPEG/WEBP quality for exports.
    'quality' => (int) env('CREATIVE_QUALITY', 90),
];
