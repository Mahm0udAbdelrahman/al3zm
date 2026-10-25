<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'users-index','users-show','users-create','users-update','users-delete',
            'roles-index','roles-show','roles-create','roles-update','roles-delete',
            'notifications-index','notifications-show','notifications-create','notifications-update','notifications-delete',
            'send_notifications-index','send_notifications-show','send_notifications-create','send_notifications-update','send_notifications-delete',
            'categories-index','categories-show','categories-create','categories-update','categories-delete',
            'sub_categories-index','sub_categories-show','sub_categories-create','sub_categories-update','sub_categories-delete',
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
