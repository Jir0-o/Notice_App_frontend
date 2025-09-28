<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingDetailspropagation extends Model
{
    protected $fillable = ['user_name', 'user_email', 'is_read', 'meeting_detail_id', 'user_id', 'sent_at'];

    public function meetingDetail()
    {
        return $this->belongsTo(MeetingDetail::class, 'meeting_detail_id');
    }
}
