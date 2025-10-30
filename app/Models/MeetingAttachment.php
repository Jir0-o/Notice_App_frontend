<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MeetingAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_detail_id',
        'file_name',
        'file_type',
        'file_path',
        'uploaded_at',
    ];

    public function meetingDetail()
    {
        return $this->belongsTo(MeetingDetail::class, 'meeting_detail_id');
    }
}
