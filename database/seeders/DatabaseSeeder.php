<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        $this->call([
            DepartmentSeeder::class,
            DesignationSeeder::class,
            UserSeeder::class,
            NoticeSeeder::class,
            AttachmentSeeder::class,
            NoticePropagationSeeder::class,
            RolePermission::class,
        ]);
    }
}
