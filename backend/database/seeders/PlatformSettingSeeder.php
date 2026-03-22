<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'odds_api_enabled',
                'value' => 'true',
                'description' => 'Whether the odds API integration is active',
            ],
            [
                'key' => 'pool_build_minutes_before_draft',
                'value' => '30',
                'description' => 'Minutes before draft time to start building the slate pool',
            ],
            [
                'key' => 'min_hours_before_game',
                'value' => '24',
                'description' => 'Minimum hours before game time for a pick to be included in the pool',
            ],
            [
                'key' => 'odds_refresh_interval_hours',
                'value' => '3',
                'description' => 'Hours between automatic odds refreshes for active slates',
            ],
            [
                'key' => 'max_leagues_default',
                'value' => '5',
                'description' => 'Default maximum leagues per user',
            ],
            [
                'key' => 'platform_commission_rate',
                'value' => '0.10',
                'description' => 'Platform commission rate on buy-ins (0.10 = 10%)',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'description' => 'Whether the platform is in maintenance mode',
            ],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
