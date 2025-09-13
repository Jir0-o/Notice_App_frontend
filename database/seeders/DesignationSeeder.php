<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Designation;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Designation::insert([
            ['name' => 'Manager', 'short_name' => 'MGR'],
            ['name' => 'Officer', 'short_name' => 'OFC'],
            ['name' => 'Assistant', 'short_name' => 'AST'],
        ]);
    }
}
