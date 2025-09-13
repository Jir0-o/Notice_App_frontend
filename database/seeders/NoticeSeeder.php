<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notice;

class NoticeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Notice::insert([
            [
                'title' => 'Holiday Announcement',
                'description' => 'Office will remain closed on 21st Feb.',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'modified_by' => null,
            ],
            [
                'title' => 'New HR Policy',
                'description' => 'HR policy updated. Please check your email.',
                'created_by' => 2,
                'created_at' => now(),
                'updated_at' => now(),
                'modified_by' => 1,
            ],
        ]);
    }
}
