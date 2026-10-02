<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Bookings
            ['view_bookings', 'Bookings', 'View bookings list and details'],
            ['create_booking', 'Bookings', 'Create manual bookings'],
            ['edit_booking', 'Bookings', 'Edit bookings & status'],
            ['cancel_booking', 'Bookings', 'Cancel bookings'],
            ['refund_booking', 'Bookings', 'Process refunds'],
            // Products
            ['manage_flights', 'Flights', 'Manage airlines, airports & flight settings'],
            ['manage_hotels', 'Hotels', 'Manage hotels & rooms'],
            ['manage_cabs', 'Cabs', 'Manage vehicles, vendors & locations'],
            ['manage_packages', 'Packages', 'Manage tour packages'],
            // Customers
            ['manage_customers', 'Customers', 'View & manage customers'],
            // Finance
            ['manage_payments', 'Payments', 'View payments'],
            ['manage_refunds', 'Payments', 'Manage refunds'],
            ['manage_pricing', 'Pricing', 'Manage pricing rules, taxes & coupons'],
            // Content
            ['manage_content', 'Content', 'Manage CMS, media & reviews'],
            // System
            ['manage_settings', 'Settings', 'Manage settings, SEO & templates'],
            ['manage_staff', 'Staff', 'Manage staff accounts'],
            ['manage_roles', 'Staff', 'Manage roles'],
            ['manage_permissions', 'Staff', 'Manage permissions'],
            ['manage_reports', 'Reports', 'View reports & exports'],
            ['view_analytics', 'Reports', 'View the website analytics dashboard'],
            ['manage_suppliers', 'Suppliers', 'Manage suppliers & API credentials'],
            ['manage_agents', 'B2B', 'Manage B2B travel agents, credit & wallets'],
            ['manage_crm', 'CRM', 'Manage leads, inquiries, follow-ups & quotations'],
            ['manage_contacts', 'CRM', 'View & manage CRM contacts, merge duplicates'],
            ['manage_crm_pipelines', 'CRM', 'Configure CRM pipelines, stages, sources & scoring'],
            ['manage_affiliates', 'Marketing', 'Manage affiliate partners, referral codes & commissions'],
            // Advertising / Marketing platform
            ['marketing.view', 'Advertising', 'View the marketing/advertising module & dashboards'],
            ['marketing.accounts', 'Advertising', 'Connect, reconnect & manage ad accounts'],
            ['marketing.campaigns.create', 'Advertising', 'Create & edit campaigns (draft)'],
            ['marketing.campaigns.publish', 'Advertising', 'Publish / activate campaigns (spends money)'],
            ['marketing.campaigns.pause', 'Advertising', 'Pause / resume campaigns'],
            ['marketing.optimize', 'Advertising', 'Approve/apply optimizations & budget changes'],
            ['marketing.ai', 'Advertising', 'Use AI campaign/creative/copilot features'],
            ['marketing.assets', 'Advertising', 'Manage creative library & assets'],
            ['marketing.analytics', 'Advertising', 'View advertising analytics & reports'],
            ['marketing.settings', 'Advertising', 'Manage marketing platform settings'],
            // Trip Operations & Arrival Management
            ['view_trip_operations', 'Trip Operations', 'View trip operations dashboards, timelines & lists'],
            ['manage_trip_operations', 'Trip Operations', 'Edit trips, timelines, statuses & operations settings'],
            ['assign_drivers', 'Trip Operations', 'Assign / reassign drivers to trips'],
            ['manage_drivers', 'Trip Operations', 'Manage the driver directory / profiles'],
            ['send_trip_communications', 'Trip Operations', 'Send driver sheets & customer itineraries (WhatsApp/email/SMS)'],
        ];

        foreach ($permissions as [$slug, $module, $description]) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('_', ' ', $slug)), 'module' => $module, 'description' => $description]
            );
        }
    }
}
