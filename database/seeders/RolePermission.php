<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RolePermission extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Check and create roles if they don't exist
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $userRole = Role::firstOrCreate(['name' => 'User']);
        $poRole = Role::firstOrCreate(['name' => 'PO']);
        $aepdRole = Role::firstOrCreate(['name' => 'AEPD']);

        // Create permissions
        $permissions = [
            ['name' => 'User List'],
            ['name' => 'Create User'],
            ['name' => 'edit User'],
            ['name' => 'delete User'],
            ['name' => 'Role List'],
            ['name' => 'Create Role'],
            ['name' => 'edit Role'],
            ['name' => 'delete Role'],
            ['name' => 'Notice List'],
            ['name' => 'Create Notice'],
            ['name' => 'Edit Notice'],
            ['name' => 'Delete Notice'],
            ['name' => 'Approve Notice'],
            ['name' => 'Reject Notice'],
            ['name' => 'View Notice PDF'],
            ['name' => 'Download Notice PDF'],
        ];

        foreach($permissions as $item){
            Permission::firstOrCreate(['name' => $item['name']]);
        }

        // Assign permissions to Super Admin
        $superAdminRole->syncPermissions(Permission::all());

        // Assign user (only if not already assigned)
        $superAdminUser = User::where('email', 'superadmin@gmail.com')->first();
        if ($superAdminUser && !$superAdminUser->hasRole('Super Admin')) {
            $superAdminUser->assignRole($superAdminRole);
        }

        // Assign permissions to Admin
        $adminRole->syncPermissions(Permission::whereIn('name', [
            'User List',
            'Create User',
            'edit User',
            'delete User',
            'Notice List',
            'Create Notice',
            'Edit Notice',
            'Delete Notice',
            'View Notice PDF',
            'Download Notice PDF',
        ])->get());

        $adminUser = User::where('email', 'admin@gmail.com')->first();
        if ($adminUser && !$adminUser->hasRole('Admin')) {
            $adminUser->assignRole($adminRole);
        }

        // Assign permissions to PO
        $poRole->syncPermissions(Permission::whereIn('name', [
            'Notice List',
            'Create Notice',
            'Edit Notice',
            'View Notice PDF',
            'Download Notice PDF',
        ])->get());

        $poUser = User::where('email', 'po@gmail.com')->first();
        if ($poUser && !$poUser->hasRole('PO')) {
            $poUser->assignRole($poRole);
        }

        // Assign permissions to AEPD
        $aepdRole->syncPermissions(Permission::whereIn('name', [
            'Notice List',
            'Approve Notice',
            'Reject Notice',
            'View Notice PDF',
            'Download Notice PDF',
        ])->get());

        $aepdUser = User::where('email', 'aepd@gmail.com')->first();
        if ($aepdUser && !$aepdUser->hasRole('AEPD')) {
            $aepdUser->assignRole($aepdRole);
        }

        // Assign permissions to regular User
        $userRole->syncPermissions(Permission::whereIn('name', [
            'User List',
            'Notice List',
            'View Notice PDF',
        ])->get());

        $regularUser = User::where('email', 'user@gmail.com')->first();
        if ($regularUser && !$regularUser->hasRole('User')) {
            $regularUser->assignRole($userRole);
        }
    }
}