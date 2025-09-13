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
        $role = Role::create([
            'name'=>'Super Admin'
        ]);

        $permissions = [
            ['name' => 'User List'],
            ['name' => 'Create User'],
            ['name' => 'edit User'],
            ['name' => 'delete User'],
            ['name' => 'Role List'],
            ['name' => 'Create Role'],
            ['name' => 'edit Role'],
            ['name' => 'delete Role'],
        ];

        foreach($permissions as $item){
            Permission::create($item);
        }

        $role->syncPermissions(Permission::all());

        $user = User::where('email', 'superadmin@gmail.com')->first();
        $user->assignRole($role);

        $roleAdmin = Role::create([
            'name' => 'Admin'
        ]);

        $roleAdmin->syncPermissions(Permission::whereIn('name', [
            'User List',
            'Create User',
            'edit User',
            'delete User',
        ])->get());

        $userAdmin = User::where('email', 'admin@gmail.com')->first();
        $userAdmin->assignRole($roleAdmin);

        $roleUser = Role::create([
            'name' => 'User'
        ]);

        $roleUser->syncPermissions(Permission::whereIn('name', [
            'User List',
        ])->get());

        $userUser = User::where('email', 'user@gmail.com')->first();
        $userUser->assignRole($roleUser);
    }
}
