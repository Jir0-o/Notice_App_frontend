<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\NoticePropagation;

class NoticePropagationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        NoticePropagation::insert([
            [
                'notice_id' => 1,
                'user_id' => 2,
                'user_email' => 'john@example.com',
                'is_read' => false,
            ],
            [
                'notice_id' => 1,
                'user_id' => null,
                'user_email' => 'externaluser@example.com',
                'is_read' => false,
            ],
        ]);
    }
}
