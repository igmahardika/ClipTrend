<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'description' => 'Full platform access']);
        $userRole = Role::firstOrCreate(['slug' => 'user'], ['name' => 'User', 'description' => 'Creator workspace access']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@cliptrend.local'],
            ['name' => 'ClipTrend Admin', 'password' => Hash::make('password'), 'upload_limit_mb' => 4096]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id, $userRole->id]);

        Setting::updateOrCreate(['group' => 'render', 'key' => 'default_preset'], [
            'type' => 'json',
            'value' => [
                'ratio' => '9:16',
                'resolution' => '1080x1920',
                'subtitle_style' => 'bold_creator',
                'smart_crop' => true,
                'background_blur' => true,
                'watermark_enabled' => false,
            ],
        ]);
    }
}
