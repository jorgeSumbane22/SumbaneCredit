<?php

namespace Database\Seeders;

use App\Support\PermissionSync;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        PermissionSync::run();
    }
}
