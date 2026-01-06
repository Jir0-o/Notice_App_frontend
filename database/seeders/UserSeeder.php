<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::insert([
            [
                'name' => 'Admin User',
                'email' => 'admin@gmail.com',
                'phone' => '01700000000',
                'password' => bcrypt('password123'),
                'designation_id' => 1,
                'department_id' => 1,
                'status' => 'active'
            ],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@gmail.com',
                'phone' => '01711111111',
                'password' => bcrypt('password123'),
                'designation_id' => 2,
                'department_id' => 2,
                'status' => 'active'
            ],
            [
                'name' => 'Regular User',
                'email' => 'user@gmail.com',
                'phone' => '01722222222',
                'password' => bcrypt('password123'),
                'designation_id' => 3,
                'department_id' => 3,
                'status' => 'inactive'
            ],
            // Add PO user
            [
                'name' => 'Project Officer (PO)',
                'email' => 'po@gmail.com',
                'phone' => '01733333333',
                'password' => bcrypt('password123'),
                'designation_id' => 3,
                'department_id' => 2,
                'status' => 'active'
            ],
            // Add AEPD user
            [
                'name' => 'AEPD Officer',
                'email' => 'aepd@gmail.com',
                'phone' => '01744444444',
                'password' => bcrypt('password123'),
                'designation_id' => 2, 
                'department_id' => 2,
                'status' => 'active'
            ],
        ]);
    }
}