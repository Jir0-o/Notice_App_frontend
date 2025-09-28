<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = ['title', 'capacity', 'is_active'];

    public function meetingDetails()
    {
        return $this->hasMany(MeetingDetail::class, 'meeting_id');
    }
}
