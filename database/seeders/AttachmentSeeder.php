<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attachment;

class AttachmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Attachment::insert([
            [
                'notice_id' => 1,
                'file_name' => 'holiday_notice.pdf',
                'file_type' => 'pdf',
                'file_path' => 'uploads/notices/holiday_notice.pdf',
                'uploaded_at' => now(),
            ],
            [
                'notice_id' => 2,
                'file_name' => 'hr_policy.docx',
                'file_type' => 'docx',
                'file_path' => 'uploads/notices/hr_policy.docx',
                'uploaded_at' => now(),
            ],
        ]);
    }
}
