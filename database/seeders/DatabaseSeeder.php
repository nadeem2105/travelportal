<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent seeders — safe to run multiple times.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            AdminSeeder::class,
            SettingSeeder::class,
            HomepageSectionSeeder::class,
            NotificationTemplateSeeder::class,
            PaymentGatewaySeeder::class,
            CrmSeeder::class,
            CreativeStudioSeeder::class,
            AgentSeeder::class,
            DemoContentSeeder::class,
        ]);
    }
}
