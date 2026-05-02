<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class PrimaryMenuSeeder extends Seeder
{
    /**
     * Default slug consumed by the marketing site header (`GET /api/public/menus/primary`).
     */
    public function run(): void
    {
        Menu::query()->firstOrCreate(
            ['slug' => 'primary'],
            ['name' => 'Primary header']
        );
    }
}
