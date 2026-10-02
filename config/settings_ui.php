<?php

/*
|--------------------------------------------------------------------------
| Settings UI structure
|--------------------------------------------------------------------------
| Drives the redesigned settings page: left category rail + right panel
| with tabs and sectioned cards. Each field persists to the settings table
| via SettingsController::update(). Types:
| text, email, number, textarea, select (options: value=>label),
| toggle, image, password (encrypt, blank keeps current).
*/

return [
    'categories' => [
        [
            'key' => 'general',
            'label' => 'General Settings',
            'description' => 'Basic company and system settings',
            'icon' => 'gear',
            'color' => 'blue',
            'tabs' => [
                [
                    'key' => 'company',
                    'label' => 'Company Details',
                    'sections' => [
                        [
                            'title' => 'Company Information',
                            'description' => 'Basic information about your company',
                            'fields' => [
                                ['key' => 'company_name', 'label' => 'Company Name', 'type' => 'text', 'required' => true],
                                ['key' => 'company_tagline', 'label' => 'Tagline', 'type' => 'text', 'placeholder' => 'Explore More. Travel Better.'],
                                ['key' => 'company_legal_name', 'label' => 'Registered / Legal Name', 'type' => 'text', 'placeholder' => 'Leemroz Travels Private Limited'],
                                ['key' => 'website_url', 'label' => 'Website URL', 'type' => 'text', 'required' => true, 'placeholder' => 'https://www.example.com'],
                                ['key' => 'business_type', 'label' => 'Business Type', 'type' => 'select', 'options' => [
                                    'ota' => 'Online Travel Agency (OTA)',
                                    'tour_operator' => 'Tour Operator',
                                    'dmc' => 'Destination Management Company (DMC)',
                                    'travel_agent' => 'Travel Agent',
                                ]],
                                ['key' => 'company_registration', 'label' => 'Company Registration No.', 'type' => 'text'],
                                ['key' => 'company_logo', 'label' => 'Company Logo', 'type' => 'image', 'help' => 'Recommended size: 300 × 100 px (PNG, JPG, SVG)', 'full' => true],
                            ],
                        ],
                        [
                            'title' => 'Platform Configuration',
                            'description' => "Configure your platform's basic settings",
                            'fields' => [
                                ['key' => 'site_name', 'label' => 'Site Name', 'type' => 'text', 'required' => true],
                                ['key' => 'site_email', 'label' => 'Site Email', 'type' => 'email', 'required' => true],
                                ['key' => 'currency_code', 'label' => 'Default Currency', 'type' => 'select', 'options' => [
                                    'INR' => 'INR (₹) — Indian Rupee',
                                    'USD' => 'USD ($) — US Dollar',
                                    'EUR' => 'EUR (€) — Euro',
                                    'GBP' => 'GBP (£) — British Pound',
                                ]],
                                ['key' => 'default_language', 'label' => 'Default Language', 'type' => 'select', 'options' => [
                                    'en' => 'English', 'hi' => 'हिन्दी (Hindi)', 'ur' => 'اردو (Urdu)',
                                ]],
                                ['key' => 'timezone', 'label' => 'Time Zone', 'type' => 'select', 'options' => [
                                    'Asia/Kolkata' => '(UTC+05:30) Asia/Kolkata',
                                    'Asia/Dubai' => '(UTC+04:00) Asia/Dubai',
                                    'UTC' => '(UTC+00:00) UTC',
                                ]],
                                ['key' => 'date_format', 'label' => 'Date Format', 'type' => 'select', 'options' => [
                                    'd M Y' => 'DD MMM YYYY (17 Sep 2025)',
                                    'd/m/Y' => 'DD/MM/YYYY (17/09/2025)',
                                    'Y-m-d' => 'YYYY-MM-DD (2025-09-17)',
                                ]],
                                ['key' => 'time_format', 'label' => 'Time Format', 'type' => 'select', 'options' => [
                                    'H:i' => '24 Hour (14:30)',
                                    'h:i A' => '12 Hour (02:30 PM)',
                                ]],
                                ['key' => 'maintenance_enabled', 'label' => 'Maintenance Mode', 'type' => 'toggle', 'help' => 'Enable to show maintenance page to users'],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'contact',
                    'label' => 'Contact Information',
                    'sections' => [
                        [
                            'title' => 'Contact Details',
                            'description' => 'How customers reach your team',
                            'fields' => [
                                ['key' => 'company_phone', 'label' => 'Phone Number', 'type' => 'text', 'required' => true],
                                ['key' => 'company_email', 'label' => 'Support Email', 'type' => 'email', 'required' => true],
                                ['key' => 'company_address', 'label' => 'Office Address', 'type' => 'textarea', 'full' => true],
                            ],
                        ],
                        [
                            'title' => 'Social Profiles',
                            'description' => 'Shown in the website footer',
                            'fields' => [
                                ['key' => 'social_facebook', 'label' => 'Facebook URL', 'type' => 'text'],
                                ['key' => 'social_instagram', 'label' => 'Instagram URL', 'type' => 'text'],
                                ['key' => 'social_youtube', 'label' => 'YouTube URL', 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'business',
                    'label' => 'Business Settings',
                    'sections' => [
                        [
                            'title' => 'Booking Rules',
                            'description' => 'Behaviour of the booking engine',
                            'fields' => [
                                ['key' => 'booking_id_prefix', 'label' => 'Booking ID Prefix', 'type' => 'text'],
                                ['key' => 'booking_expiry_minutes', 'label' => 'Booking Expiry (minutes)', 'type' => 'number'],
                                ['key' => 'refund_processing_days', 'label' => 'Refund Processing (days)', 'type' => 'text'],
                                ['key' => 'auto_confirm_packages', 'label' => 'Auto-confirm Packages', 'type' => 'toggle', 'help' => 'Confirm package bookings immediately after payment'],
                            ],
                        ],
                        [
                            'title' => 'Payments Copy',
                            'description' => 'Instructions shown to customers',
                            'fields' => [
                                ['key' => 'payment_instructions', 'label' => 'Payment Instructions', 'type' => 'textarea', 'full' => true],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'misc',
                    'label' => 'Miscellaneous',
                    'sections' => [
                        [
                            'title' => 'Homepage Copy',
                            'description' => 'Hero and trust badges on the homepage',
                            'fields' => [
                                ['key' => 'hero_title_line1', 'label' => 'Hero Title (line 1)', 'type' => 'text'],
                                ['key' => 'hero_title_line2', 'label' => 'Hero Title (line 2)', 'type' => 'text'],
                                ['key' => 'hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'full' => true],
                                ['key' => 'trust_best_price', 'label' => 'Trust Badge 1', 'type' => 'text'],
                                ['key' => 'trust_easy_booking', 'label' => 'Trust Badge 2', 'type' => 'text'],
                                ['key' => 'trust_support', 'label' => 'Trust Badge 3', 'type' => 'text'],
                                ['key' => 'trust_secure', 'label' => 'Trust Badge 4', 'type' => 'text'],
                            ],
                        ],
                        [
                            'title' => 'Footer & App Links',
                            'description' => 'Footer text and mobile app destinations',
                            'fields' => [
                                ['key' => 'footer_about', 'label' => 'Footer About Text', 'type' => 'textarea', 'full' => true],
                                ['key' => 'footer_copyright', 'label' => 'Copyright Line', 'type' => 'text'],
                                ['key' => 'app_download_url', 'label' => 'App Download URL', 'type' => 'text'],
                                ['key' => 'app_android_url', 'label' => 'Android App URL', 'type' => 'text'],
                                ['key' => 'app_ios_url', 'label' => 'iOS App URL', 'type' => 'text'],
                                ['key' => 'newsletter_title', 'label' => 'Newsletter Title', 'type' => 'text'],
                                ['key' => 'newsletter_subtitle', 'label' => 'Newsletter Subtitle', 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'legal',
            'label' => 'Company & Legal',
            'description' => 'Business details, GST, policies',
            'icon' => 'briefcase',
            'color' => 'green',
            'tabs' => [
                [
                    'key' => 'legal',
                    'label' => 'Legal & Invoicing',
                    'sections' => [
                        [
                            'title' => 'Legal Documents',
                            'description' => 'Details printed on invoices and policies',
                            'fields' => [
                                ['key' => 'company_registration', 'label' => 'Company Registration No.', 'type' => 'text'],
                                ['key' => 'invoice_terms', 'label' => 'Invoice Terms', 'type' => 'textarea', 'full' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'localization',
            'label' => 'Currency & Localization',
            'description' => 'Currency, language, time zone',
            'icon' => 'globe',
            'color' => 'amber',
            'tabs' => [
                [
                    'key' => 'locale',
                    'label' => 'Localization',
                    'sections' => [
                        [
                            'title' => 'Regional Defaults',
                            'description' => 'Currency and formatting for the storefront',
                            'fields' => [
                                ['key' => 'currency_code', 'label' => 'Default Currency', 'type' => 'select', 'options' => [
                                    'INR' => 'INR (₹) — Indian Rupee',
                                    'USD' => 'USD ($) — US Dollar',
                                    'EUR' => 'EUR (€) — Euro',
                                    'GBP' => 'GBP (£) — British Pound',
                                ]],
                                ['key' => 'default_language', 'label' => 'Default Language', 'type' => 'select', 'options' => [
                                    'en' => 'English', 'hi' => 'हिन्दी (Hindi)', 'ur' => 'اردو (Urdu)',
                                ]],
                                ['key' => 'timezone', 'label' => 'Time Zone', 'type' => 'select', 'options' => [
                                    'Asia/Kolkata' => '(UTC+05:30) Asia/Kolkata',
                                    'Asia/Dubai' => '(UTC+04:00) Asia/Dubai',
                                    'UTC' => '(UTC+00:00) UTC',
                                ]],
                                ['key' => 'date_format', 'label' => 'Date Format', 'type' => 'select', 'options' => [
                                    'd M Y' => 'DD MMM YYYY (17 Sep 2025)',
                                    'd/m/Y' => 'DD/MM/YYYY (17/09/2025)',
                                    'Y-m-d' => 'YYYY-MM-DD (2025-09-17)',
                                ]],
                                ['key' => 'time_format', 'label' => 'Time Format', 'type' => 'select', 'options' => [
                                    'H:i' => '24 Hour (14:30)',
                                    'h:i A' => '12 Hour (02:30 PM)',
                                ]],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'branding',
            'label' => 'Branding & Appearance',
            'description' => 'Logo, colors, theme, emails',
            'icon' => 'palette',
            'color' => 'violet',
            'tabs' => [
                [
                    'key' => 'branding',
                    'label' => 'Branding',
                    'sections' => [
                        [
                            'title' => 'Brand Assets',
                            'description' => 'Logo and favicon used across the platform',
                            'fields' => [
                                ['key' => 'company_logo', 'label' => 'Company Logo', 'type' => 'image', 'help' => 'Recommended size: 300 × 100 px', 'full' => true],
                                ['key' => 'company_favicon', 'label' => 'Favicon', 'type' => 'image', 'help' => 'Recommended size: 64 × 64 px', 'full' => true],
                                ['key' => 'og_default_image', 'label' => 'Social Share Image (OG)', 'type' => 'image', 'help' => 'Recommended size: 1200 × 630 px', 'full' => true],
                            ],
                        ],
                        [
                            'title' => 'Hero Presentation',
                            'description' => 'Script accents shown on the homepage hero',
                            'fields' => [
                                ['key' => 'hero_script_line1', 'label' => 'Script Line 1', 'type' => 'text'],
                                ['key' => 'hero_script_line2', 'label' => 'Script Line 2', 'type' => 'text'],
                                ['key' => 'why_script_line1', 'label' => 'Why Choose Script (line 1)', 'type' => 'text'],
                                ['key' => 'why_script_line2', 'label' => 'Why Choose Script (line 2)', 'type' => 'text'],
                                ['key' => 'why_script_line3', 'label' => 'Why Choose Script (line 3)', 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'payment',
            'label' => 'Payment Settings',
            'description' => 'Payment gateways, wallet, refunds',
            'icon' => 'card',
            'color' => 'red',
            'tabs' => [
                [
                    'key' => 'payment',
                    'label' => 'Payments',
                    'sections' => [
                        [
                            'title' => 'Gateway Configuration',
                            'description' => 'Enable gateways and manage keys',
                            'fields' => [],
                            'links' => [
                                ['route' => 'admin.gateways.index', 'label' => 'Payment Gateways', 'description' => 'Enable Razorpay or the sandbox gateway and manage API secrets'],
                            ],
                        ],
                        [
                            'title' => 'Customer Instructions',
                            'description' => 'Text shown during checkout',
                            'fields' => [
                                ['key' => 'payment_instructions', 'label' => 'Payment Instructions', 'type' => 'textarea', 'full' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'suppliers',
            'label' => 'Supplier Integrations',
            'description' => 'APIs, credentials, markup',
            'icon' => 'plug',
            'color' => 'teal',
            'tabs' => [
                [
                    'key' => 'suppliers',
                    'label' => 'Integrations',
                    'sections' => [
                        [
                            'title' => 'Connected Suppliers',
                            'description' => 'Flight, hotel and cab APIs with credentials, priority and markup',
                            'fields' => [],
                            'links' => [
                                ['route' => 'admin.suppliers.index', 'label' => 'Suppliers & APIs', 'description' => 'Add suppliers, manage encrypted credentials, environment, timeout and retries'],
                                ['route' => 'admin.pricing-rules.index', 'label' => 'Pricing Rules & Taxes', 'description' => 'Markup, commission, service fees and tax configuration'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'pricing',
            'label' => 'Pricing & Commission',
            'description' => 'Markup, tax, service fees',
            'icon' => 'percent',
            'color' => 'amber',
            'tabs' => [
                [
                    'key' => 'pricing',
                    'label' => 'Pricing',
                    'sections' => [
                        [
                            'title' => 'Pricing Engine',
                            'description' => 'Server-side rules applied to every booking',
                            'fields' => [],
                            'links' => [
                                ['route' => 'admin.pricing-rules.index', 'label' => 'Pricing Rules', 'description' => 'Percentage or fixed markup, commission and fees per product/supplier'],
                                ['route' => 'admin.taxes.index', 'label' => 'Taxes & Charges', 'description' => 'GST and convenience fees — nothing is hardcoded'],
                                ['route' => 'admin.coupons.index', 'label' => 'Coupons', 'description' => 'Discount codes with limits and validity'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'booking',
            'label' => 'Booking Settings',
            'description' => 'Booking rules, cancellation, limits',
            'icon' => 'calendar',
            'color' => 'indigo',
            'tabs' => [
                [
                    'key' => 'booking',
                    'label' => 'Bookings',
                    'sections' => [
                        [
                            'title' => 'Booking Engine',
                            'description' => 'References, expiry and confirmations',
                            'fields' => [
                                ['key' => 'booking_id_prefix', 'label' => 'Booking ID Prefix', 'type' => 'text'],
                                ['key' => 'booking_expiry_minutes', 'label' => 'Booking Expiry (minutes)', 'type' => 'number'],
                                ['key' => 'refund_processing_days', 'label' => 'Refund Processing (days)', 'type' => 'text'],
                                ['key' => 'auto_confirm_packages', 'label' => 'Auto-confirm Packages', 'type' => 'toggle'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'email',
            'label' => 'Email & Notifications',
            'description' => 'Templates, SMS, WhatsApp, push',
            'icon' => 'mail',
            'color' => 'red',
            'tabs' => [
                [
                    'key' => 'email',
                    'label' => 'Email',
                    'sections' => [
                        [
                            'title' => 'SMTP Configuration',
                            'description' => 'Mail server credentials used for all platform emails',
                            'fields' => [
                                ['key' => 'mail_mailer', 'label' => 'Mail Mailer', 'type' => 'select', 'options' => [
                                    'smtp' => 'SMTP', 'log' => 'Log', 'sendmail' => 'Sendmail',
                                ]],
                                ['key' => 'mail_host', 'label' => 'Mail Host', 'type' => 'text', 'placeholder' => 'smtp.mailtrap.io'],
                                ['key' => 'mail_port', 'label' => 'Mail Port', 'type' => 'number', 'placeholder' => '587'],
                                ['key' => 'mail_encryption', 'label' => 'Encryption', 'type' => 'select', 'options' => [
                                    'tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None',
                                ]],
                                ['key' => 'mail_username', 'label' => 'Username', 'type' => 'text'],
                                ['key' => 'mail_password', 'label' => 'Password', 'type' => 'password'],
                                ['key' => 'mail_notifications_enabled', 'label' => 'Email Notifications', 'type' => 'toggle', 'help' => 'Send transactional emails (bookings, OTP, cancellations)'],
                            ],
                            'links' => [
                                ['route' => 'admin.notification-templates.index', 'label' => 'Notification Templates', 'description' => 'Edit booking, cancellation, OTP and welcome email templates'],
                            ],
                        ],
                        [
                            'title' => 'Outgoing Mail',
                            'description' => 'Sender identity for all platform emails',
                            'fields' => [
                                ['key' => 'mail_from_name', 'label' => 'From Name', 'type' => 'text'],
                                ['key' => 'mail_from_address', 'label' => 'From Address', 'type' => 'email'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'users',
            'label' => 'User Management',
            'description' => 'Roles, permissions, staff',
            'icon' => 'users',
            'color' => 'blue',
            'tabs' => [
                [
                    'key' => 'users',
                    'label' => 'Team & Access',
                    'sections' => [
                        [
                            'title' => 'Staff & Access Control',
                            'description' => 'Manage who can do what inside the panel',
                            'fields' => [],
                            'links' => [
                                ['route' => 'admin.staff.index', 'label' => 'Staff', 'description' => 'Create staff accounts and assign roles'],
                                ['route' => 'admin.roles.index', 'label' => 'Roles & Permissions', 'description' => 'Fine-grained server-enforced permissions per role'],
                                ['route' => 'admin.activity.index', 'label' => 'Activity Logs', 'description' => 'Every admin action with user, IP and timestamp'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'security',
            'label' => 'Security',
            'description' => 'Authentication, 2FA, IP restrictions',
            'icon' => 'shield',
            'color' => 'rose',
            'tabs' => [
                [
                    'key' => 'security',
                    'label' => 'Security',
                    'sections' => [
                        [
                            'title' => 'Platform Availability',
                            'description' => 'Take the storefront offline when needed',
                            'fields' => [
                                ['key' => 'maintenance_enabled', 'label' => 'Maintenance Mode', 'type' => 'toggle', 'help' => 'Enable to show maintenance page to users'],
                                ['key' => 'maintenance_message', 'label' => 'Maintenance Message', 'type' => 'textarea', 'full' => true],
                            ],
                            'links' => [
                                ['route' => 'admin.activity.index', 'label' => 'Activity Logs', 'description' => 'Audit trail of all admin actions'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'seo',
            'label' => 'SEO & Analytics',
            'description' => 'Meta tags, analytics, tracking',
            'icon' => 'chart',
            'color' => 'teal',
            'tabs' => [
                [
                    'key' => 'seo',
                    'label' => 'SEO',
                    'sections' => [
                        [
                            'title' => 'Global SEO',
                            'description' => 'Defaults inherited by every page',
                            'fields' => [
                                ['key' => 'seo_meta_description', 'label' => 'Global Meta Description', 'type' => 'textarea', 'full' => true],
                                ['key' => 'seo_analytics_code', 'label' => 'Analytics / Tracking Code', 'type' => 'textarea', 'full' => true, 'help' => 'Paste your GA4 / GTM snippet — injected on every page'],
                            ],
                            'links' => [
                                ['route' => 'admin.seo.index', 'label' => 'SEO Manager', 'description' => 'Per-page titles, meta, OG tags and JSON-LD schema'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'api',
            'label' => 'API & Webhooks',
            'description' => 'API keys, webhooks, rate limits',
            'icon' => 'code',
            'color' => 'blue',
            'tabs' => [
                [
                    'key' => 'api',
                    'label' => 'API',
                    'sections' => [
                        [
                            'title' => 'Integrations & Webhooks',
                            'description' => 'REST API v1 and payment webhooks',
                            'fields' => [],
                            'links' => [
                                ['route' => 'admin.suppliers.index', 'label' => 'Supplier APIs', 'description' => 'Amadeus, TBO, Akbar adapters with encrypted credentials'],
                                ['route' => 'admin.gateways.index', 'label' => 'Payment Webhooks', 'description' => 'Razorpay webhook secrets — signatures verified and replay-protected'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'key' => 'system',
            'label' => 'System & Maintenance',
            'description' => 'Cache, logs, backup, updates',
            'icon' => 'wrench',
            'color' => 'slate',
            'tabs' => [
                [
                    'key' => 'system',
                    'label' => 'System',
                    'sections' => [
                        [
                            'title' => 'Maintenance Window',
                            'description' => 'Show a friendly page while you work',
                            'fields' => [
                                ['key' => 'maintenance_enabled', 'label' => 'Maintenance Mode', 'type' => 'toggle', 'help' => 'Enable to show maintenance page to users'],
                                ['key' => 'maintenance_message', 'label' => 'Maintenance Message', 'type' => 'textarea', 'full' => true],
                            ],
                            'links' => [
                                ['route' => 'admin.activity.index', 'label' => 'Activity Logs', 'description' => 'Full audit trail of admin actions'],
                                ['route' => 'admin.reports.index', 'label' => 'Reports', 'description' => 'Sales, revenue and supplier performance'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
